<?php

namespace App\Http\Controllers;

use App\Mail\MfaOtpMail;
use App\Models\User;
use App\Services\OtpService;
use App\Services\SmsService;
use App\Services\TotpService;
use App\Services\WebAuthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MfaController extends Controller
{
    private const MFA_MAX_ATTEMPTS = 5;
    private const MFA_DECAY_SECONDS = 300;

    public function __construct(
        private TotpService   $totp,
        private OtpService    $otp,
        private SmsService    $sms,
        private WebAuthnService $webAuthn,
    ) {}

    // ══════════════════════════════════════════════════════════════════
    // PROFILE — TOTP
    // ══════════════════════════════════════════════════════════════════

    public function totpEnableForm(Request $request): View
    {
        $user   = $request->user();
        $secret = $request->session()->get('mfa_totp_setup_secret')
            ?? $this->totp->generateSecret();
        $request->session()->put('mfa_totp_setup_secret', $secret);

        $otpauthUrl = $this->totp->getOtpauthUrl($user->email, $secret, config('app.name'));

        return view('profile.mfa-enable', compact('secret', 'otpauthUrl'));
    }

    public function totpEnable(Request $request): RedirectResponse
    {
        $request->validate(['code' => 'required|string']);

        $secret = $request->session()->get('mfa_totp_setup_secret');
        if (!$secret) {
            return redirect()->route('mfa.totp.enable.form')
                ->withErrors(['code' => 'Session expirée, recommencez.']);
        }

        if (!$this->totp->verify($secret, $request->input('code'))) {
            return back()->withErrors(['code' => 'Code invalide. Vérifiez l\'heure de votre appareil.']);
        }

        // Generate recovery codes
        $plainCodes  = $this->generateRecoveryCodes();
        $hashedCodes = array_map(fn($c) => hash('sha256', $c), $plainCodes);

        $request->user()->update([
            'two_factor_secret'          => $secret,
            'two_factor_confirmed_at'    => now(),
            'two_factor_recovery_codes'  => json_encode($hashedCodes),
        ]);
        $request->session()->forget('mfa_totp_setup_secret');
        $request->session()->put('mfa_recovery_codes', $plainCodes);

        return redirect()->route('mfa.recovery-codes');
    }

    public function totpDisable(Request $request): RedirectResponse
    {
        $request->validate(['password' => 'required|current_password']);

        $request->user()->update([
            'two_factor_secret'          => null,
            'two_factor_confirmed_at'    => null,
            'two_factor_recovery_codes'  => null,
        ]);

        return redirect()->route('profile.edit')->with('status', 'mfa-totp-disabled');
    }

    public function recoveryCodesShow(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->get('mfa_recovery_codes');
        if (!$codes) {
            return redirect()->route('profile.edit');
        }
        return view('profile.mfa-recovery-codes', compact('codes'));
    }

    public function recoveryCodesConfirm(Request $request): RedirectResponse
    {
        $request->session()->forget('mfa_recovery_codes');
        return redirect()->route('profile.edit')->with('status', 'mfa-totp-enabled');
    }

    public function recoveryCodesRegenerate(Request $request): RedirectResponse
    {
        $request->validate(['password' => 'required|current_password']);

        $plainCodes  = $this->generateRecoveryCodes();
        $hashedCodes = array_map(fn($c) => hash('sha256', $c), $plainCodes);

        $request->user()->update(['two_factor_recovery_codes' => json_encode($hashedCodes)]);
        $request->session()->put('mfa_recovery_codes', $plainCodes);

        return redirect()->route('mfa.recovery-codes');
    }

    private function generateRecoveryCodes(int $count = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtolower(Str::random(5) . '-' . Str::random(5));
        }
        return $codes;
    }

    // ══════════════════════════════════════════════════════════════════
    // PROFILE — EMAIL OTP
    // ══════════════════════════════════════════════════════════════════

    public function emailEnable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $code = $this->otp->generate($user, 'email');

        try {
            Mail::to($user->email)->send(new MfaOtpMail($code, 'email'));
        } catch (\Throwable $e) {
            return redirect()->route('profile.edit')
                ->withErrors(['mfa_email' => 'Impossible d\'envoyer l\'email. Vérifiez la configuration mail du site.']);
        }

        return redirect()->route('mfa.email.confirm')->with('status', 'code-sent');
    }

    public function emailConfirmForm(Request $request): View
    {
        return view('profile.mfa-confirm-email');
    }

    public function emailConfirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => 'required|string']);

        if (!$this->otp->verify($request->user(), 'email', $request->input('code'))) {
            return back()->withErrors(['code' => 'Code invalide ou expiré.']);
        }

        $request->user()->update(['mfa_email_enabled' => true]);

        return redirect()->route('profile.edit')->with('status', 'mfa-email-enabled');
    }

    public function emailDisable(Request $request): RedirectResponse
    {
        $request->validate(['password' => 'required|current_password']);
        $request->user()->update(['mfa_email_enabled' => false]);

        return redirect()->route('profile.edit')->with('status', 'mfa-email-disabled');
    }

    // ══════════════════════════════════════════════════════════════════
    // PROFILE — SMS OTP
    // ══════════════════════════════════════════════════════════════════

    public function smsSetupForm(Request $request): View
    {
        $driver = $this->sms->driver();
        return view('profile.mfa-sms-setup', compact('driver'));
    }

    public function smsSetup(Request $request): RedirectResponse
    {
        $driver   = $this->sms->driver();
        $isNtfy   = $driver === 'ntfy';

        // Validation adaptée : ntfy = topic alphanumérique, autres = numéro de téléphone
        $request->validate(['contact' => $isNtfy
            ? 'required|string|max:100|regex:/^[a-zA-Z0-9_\-]+$/'
            : 'required|string|max:20|regex:/^\+?[0-9\s\-]{7,20}$/']);

        $contact = $isNtfy
            ? $request->input('contact')
            : preg_replace('/[\s\-]/', '', $request->input('contact'));

        $user = $request->user();
        $code = $this->otp->generate($user, 'sms');

        $sent = $this->sms->send($contact, 'Votre code ' . config('app.name') . ' : ' . $code);
        if (!$sent) {
            return back()->withErrors(['contact' => $isNtfy
                ? 'Impossible d\'envoyer la notification ntfy. Vérifiez le topic et la configuration.'
                : 'Impossible d\'envoyer le message. Vérifiez la configuration SMS.']);
        }

        $request->session()->put('mfa_sms_setup_phone', $contact);

        return redirect()->route('mfa.sms.confirm')->with('status', 'sms-sent');
    }

    public function smsConfirmForm(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('mfa_sms_setup_phone')) {
            return redirect()->route('mfa.sms.setup');
        }
        return view('profile.mfa-confirm-sms');
    }

    public function smsConfirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => 'required|string']);

        $phone = $request->session()->get('mfa_sms_setup_phone');
        if (!$phone) return redirect()->route('mfa.sms.setup');

        if (!$this->otp->verify($request->user(), 'sms', $request->input('code'))) {
            return back()->withErrors(['code' => 'Code invalide ou expiré.']);
        }

        $request->user()->update(['mfa_sms_enabled' => true, 'mfa_phone' => $phone]);
        $request->session()->forget('mfa_sms_setup_phone');

        return redirect()->route('profile.edit')->with('status', 'mfa-sms-enabled');
    }

    public function smsDisable(Request $request): RedirectResponse
    {
        $request->validate(['password' => 'required|current_password']);
        $request->user()->update(['mfa_sms_enabled' => false, 'mfa_phone' => null]);

        return redirect()->route('profile.edit')->with('status', 'mfa-sms-disabled');
    }

    // ══════════════════════════════════════════════════════════════════
    // PROFILE — WebAuthn
    // ══════════════════════════════════════════════════════════════════

    public function webAuthnRegisterOptions(Request $request): JsonResponse
    {
        $options = $this->webAuthn->generateRegistrationOptions($request->user());
        $request->session()->put('webauthn_reg_challenge', $options['_challenge']);
        unset($options['_challenge']);

        return response()->json($options);
    }

    public function webAuthnRegister(Request $request): RedirectResponse
    {
        $request->validate([
            'attestation' => 'required|string',
            'key_name'    => 'nullable|string|max:50',
        ]);

        $challenge = $request->session()->get('webauthn_reg_challenge');
        if (!$challenge) {
            return back()->withErrors(['key' => 'Session expirée, recommencez.']);
        }

        $attestation = json_decode($request->input('attestation'), true);
        $credential  = $this->webAuthn->verifyRegistration(
            $attestation,
            $challenge,
            $request->input('key_name'),
        );

        if (!$credential) {
            return back()->withErrors(['key' => 'Enregistrement de la clé échoué.']);
        }

        $user        = $request->user();
        $credentials = $user->webAuthnCredentials();
        $credentials[] = $credential;
        $user->update(['mfa_webauthn_credentials' => json_encode($credentials)]);
        $request->session()->forget('webauthn_reg_challenge');

        return redirect()->route('profile.edit')->with('status', 'mfa-webauthn-added');
    }

    public function webAuthnRemove(Request $request, string $credentialId): RedirectResponse
    {
        $request->validate(['password' => 'required|current_password']);

        $user        = $request->user();
        $credentials = array_filter(
            $user->webAuthnCredentials(),
            fn($c) => $c['id'] !== $credentialId
        );
        $user->update(['mfa_webauthn_credentials' => json_encode(array_values($credentials))]);

        return redirect()->route('profile.edit')->with('status', 'mfa-webauthn-removed');
    }

    // ══════════════════════════════════════════════════════════════════
    // LOGIN CHALLENGE
    // ══════════════════════════════════════════════════════════════════

    public function challengeForm(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('mfa_user_id')) {
            return redirect()->route('login');
        }
        $user = User::find($request->session()->get('mfa_user_id'));
        if (!$user) return redirect()->route('login');

        $methods = $user->enabledMfaMethods();

        // Pre-generate WebAuthn assertion options if webauthn is enabled
        $webAuthnOptions = null;
        if (in_array('webauthn', $methods)) {
            $opts = $this->webAuthn->generateAssertionOptions($user);
            $request->session()->put('webauthn_assert_challenge', $opts['_challenge']);
            unset($opts['_challenge']);
            $webAuthnOptions = $opts;
        }

        return view('auth.mfa-challenge', compact('methods', 'webAuthnOptions'));
    }

    public function challengeSend(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('mfa_user_id');
        if (!$userId) return redirect()->route('login');

        $user   = User::findOrFail($userId);
        $method = $request->input('method');

        if ($method === 'email' && $user->hasEmailMfa()) {
            if (!$this->otp->canResend($user, 'email')) {
                return back()->with('error', 'Attendez 60 secondes avant de renvoyer un code.');
            }
            $code = $this->otp->generate($user, 'email');
            try { Mail::to($user->email)->send(new MfaOtpMail($code, 'email')); } catch (\Throwable) {}
            return back()->with('code_sent', 'email');
        }

        if ($method === 'sms' && $user->hasSmsMfa()) {
            if (!$this->otp->canResend($user, 'sms')) {
                return back()->with('error', 'Attendez 60 secondes avant de renvoyer un code.');
            }
            $code = $this->otp->generate($user, 'sms');
            $this->sms->send($user->mfa_phone, 'Votre code ' . config('app.name') . ' : ' . $code);
            return back()->with('code_sent', 'sms');
        }

        return back();
    }

    public function challenge(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('mfa_user_id');
        if (!$userId) return redirect()->route('login');

        if ($locked = $this->mfaLockout($userId)) return $locked;

        $user   = User::findOrFail($userId);
        $method = $request->input('method');
        $code   = $request->input('code');

        $verified = match ($method) {
            'totp'  => $user->hasTotp() && $this->totp->verify($user->two_factor_secret, $code ?? ''),
            'email' => $user->hasEmailMfa() && $this->otp->verify($user, 'email', $code ?? ''),
            'sms'   => $user->hasSmsMfa() && $this->otp->verify($user, 'sms', $code ?? ''),
            default => false,
        };

        if (!$verified) {
            RateLimiter::hit($this->mfaAttemptKey($userId), self::MFA_DECAY_SECONDS);
            return back()->withErrors(['code' => 'Code invalide ou expiré.']);
        }

        $this->completeMfaLogin($request, $user);

        return redirect()->intended(route('reservations.index'));
    }

    public function challengeWebAuthn(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('mfa_user_id');
        if (!$userId) return redirect()->route('login');

        $user      = User::findOrFail($userId);
        $challenge = $request->session()->get('webauthn_assert_challenge');
        if (!$challenge) return back()->withErrors(['key' => 'Session expirée.']);

        $assertion = json_decode($request->input('assertion', ''), true);
        if (!$assertion) return back()->withErrors(['key' => 'Données invalides.']);

        if (!$this->webAuthn->verifyAssertion($user, $assertion, $challenge)) {
            return back()->withErrors(['key' => 'Authentification par clé physique échouée.']);
        }

        $request->session()->forget('webauthn_assert_challenge');
        $this->completeMfaLogin($request, $user);

        return redirect()->intended(route('reservations.index'));
    }

    public function challengeRecoveryForm(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('mfa_user_id')) {
            return redirect()->route('login');
        }
        return view('auth.mfa-challenge-recovery');
    }

    public function challengeRecovery(Request $request): RedirectResponse
    {
        $request->validate(['code' => 'required|string']);

        $userId = $request->session()->get('mfa_user_id');
        if (!$userId) return redirect()->route('login');

        if ($locked = $this->mfaLockout($userId)) return $locked;

        $user        = User::findOrFail($userId);
        $storedCodes = json_decode($user->two_factor_recovery_codes ?? '[]', true);
        $input       = strtolower(trim(preg_replace('/\s+/', '', $request->input('code'))));
        $hashed      = hash('sha256', $input);

        $found = false;
        foreach ($storedCodes as $i => $code) {
            if ($code !== null && $code === $hashed) {
                $storedCodes[$i] = null; // code utilisé — invalide pour la prochaine fois
                $found = true;
                break;
            }
        }

        if (!$found) {
            RateLimiter::hit($this->mfaAttemptKey($userId), self::MFA_DECAY_SECONDS);
            return back()->withErrors(['code' => 'Code de récupération invalide ou déjà utilisé.']);
        }

        $user->update(['two_factor_recovery_codes' => json_encode($storedCodes)]);
        $this->completeMfaLogin($request, $user);

        return redirect()->intended(route('reservations.index'));
    }

    private function mfaAttemptKey(int $userId): string
    {
        return "mfa-attempts:{$userId}";
    }

    /** Bloque les essais de codes apres trop d'echecs, par compte (et non par IP : le lycee partage une adresse). */
    private function mfaLockout(int $userId): ?RedirectResponse
    {
        $key = $this->mfaAttemptKey($userId);
        if (!RateLimiter::tooManyAttempts($key, self::MFA_MAX_ATTEMPTS)) return null;

        $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
        return back()->withErrors(['code' => "Trop de tentatives. Réessayez dans {$minutes} minute(s)."]);
    }

    private function completeMfaLogin(Request $request, User $user): void
    {
        RateLimiter::clear($this->mfaAttemptKey($user->id));
        $remember = $request->session()->pull('mfa_remember', false);
        $request->session()->forget(['mfa_user_id', 'mfa_methods']);
        Auth::login($user, $remember);
        $request->session()->regenerate();
    }

    // ══════════════════════════════════════════════════════════════════
    // Kept for backward compat (old TOTP-only routes)
    // ══════════════════════════════════════════════════════════════════

    public function enableForm(Request $request): View
    {
        return $this->totpEnableForm($request);
    }

    public function enable(Request $request): RedirectResponse
    {
        return $this->totpEnable($request);
    }

    public function disable(Request $request): RedirectResponse
    {
        return $this->totpDisable($request);
    }
}
