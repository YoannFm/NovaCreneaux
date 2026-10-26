<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use App\Models\Role;
use App\Rules\TrustedProxyList;
use App\Services\ActivityLogger;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function __construct(private SettingService $settings) {}

    // ---------------------------------------------------------------
    // General
    // ---------------------------------------------------------------
    public function general()
    {
        return view('admin.settings.general', [
            's'         => $this->settings->all(),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function updateGeneral(Request $request)
    {
        $data = $request->validate([
            'app_name'         => 'required|string|max:100',
            'site_url'         => 'nullable|url|max:255',
            'site_description' => 'nullable|string',
            'site_keywords'    => 'nullable|string|max:500',
            'timezone'         => 'nullable|string|max:100',
            'locale'           => 'nullable|in:fr,en',
        ]);
        foreach ($data as $key => $value) $this->settings->set($key, $value ?? '');
        ActivityLogger::log('Paramètres modifiés', 'Général');
        return back()->with('success', 'Paramètres généraux enregistrés.');
    }

    public function uploadLogo(Request $request)
    {
        $request->validate(['logo' => 'required|image|max:2048']);
        $file = $request->file('logo');
        $dir = public_path('uploads/logo');
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $old = $this->settings->get('site_logo');
        if ($old && file_exists(public_path($old))) @unlink(public_path($old));
        $filename = 'logo_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($dir, $filename);
        $this->settings->set('site_logo', 'uploads/logo/' . $filename);
        return back()->with('success', 'Logo mis à jour.');
    }

    public function deleteLogo()
    {
        $old = $this->settings->get('site_logo');
        if ($old && file_exists(public_path($old))) @unlink(public_path($old));
        $this->settings->set('site_logo', '');
        return back()->with('success', 'Logo supprimé.');
    }

    // ---------------------------------------------------------------
    // Home
    // ---------------------------------------------------------------
    public function home()
    {
        return view('admin.settings.home', ['s' => $this->settings->all()]);
    }

    public function updateHome(Request $request)
    {
        $data = $request->validate(['home_message' => 'nullable|string']);
        $this->settings->set('home_message', $data['home_message'] ?? '');
        return back()->with('success', 'Message d\'accueil enregistré.');
    }

    // ---------------------------------------------------------------
    // Auth
    // ---------------------------------------------------------------
    public function auth()
    {
        return view('admin.settings.auth', ['s' => $this->settings->all()]);
    }

    public function updateAuth(Request $request)
    {
        $request->validate([
            'registration_terms'          => 'nullable|string',
            'registration_open'           => 'boolean',
            'allow_name_change'           => 'boolean',
            'allow_account_deletion'      => 'boolean',
            'allowed_email_domain'        => 'nullable|array',
            'allowed_email_domain.*'      => 'nullable|string|max:100|regex:/^[a-zA-Z0-9\-\.]+$/',
        ]);

        // Consolider les domaines en CSV
        $domains = array_values(array_filter(array_map(
            'trim',
            $request->input('allowed_email_domain', [])
        )));
        $this->settings->set('allowed_email_domain', implode(',', $domains));

        $this->settings->set('registration_terms',     $request->input('registration_terms', ''));
        $this->settings->set('registration_open',      $request->boolean('registration_open') ? '1' : '0');
        $this->settings->set('allow_name_change',      $request->boolean('allow_name_change') ? '1' : '0');
        $this->settings->set('allow_account_deletion', $request->boolean('allow_account_deletion') ? '1' : '0');

        ActivityLogger::log('Paramètres modifiés', 'Authentification');
        return back()->with('success', 'Paramètres d\'authentification enregistrés.');
    }

    // ---------------------------------------------------------------
    // Security
    // ---------------------------------------------------------------
    public function security(Request $request)
    {
        return view('admin.settings.security', [
            's' => $this->settings->all(),
            'secrets' => $this->settings->secretsStatus(),
            'proxy' => $this->proxyDiagnostic($request),
        ]);
    }

    public function updateProxies(Request $request)
    {
        $data = $request->validate(['trusted_proxies' => ['nullable', 'string', 'max:255', new TrustedProxyList]]);

        $this->settings->set('trusted_proxies', implode(',', TrustedProxyList::parse((string) ($data['trusted_proxies'] ?? ''))));
        ActivityLogger::log('Paramètres modifiés', 'Proxys de confiance');
        return back()->with('success', 'Proxys de confiance enregistrés. Rechargez la page pour vérifier l\'adresse détectée.');
    }

    /** Ce que le site voit de la connexion en cours : aide a choisir le proxy a declarer. */
    private function proxyDiagnostic(Request $request): array
    {
        $remote = $request->server('REMOTE_ADDR');
        $forwarded = $request->header('X-Forwarded-For');
        $seen = $request->ip();

        // Un proxy local a une adresse privee ou de boucle locale ; une adresse publique est celle du visiteur lui-meme
        $remoteIsLocalProxy = filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;

        return [
            'remote' => $remote,
            'forwarded' => $forwarded,
            'seen' => $seen,
            'direct' => !$remoteIsLocalProxy,
            // Une adresse reelle annoncee par un proxy local, mais ignoree faute de confiance
            'suggestion' => $remoteIsLocalProxy && $forwarded && $seen === $remote ? $remote : null,
        ];
    }

    public function updateSecurity(Request $request)
    {
        // Toggles
        if (!$request->has('_sms_save')) {
            $keys = ['force_email_verification', 'require_2fa_admin'];
            foreach ($keys as $key) {
                $this->settings->set($key, $request->boolean($key) ? '1' : '0');
            }
        }

        // SMS/notification config
        if ($request->has('sms_driver') || $request->has('_sms_save')) {
            $data = $request->validate([
                'sms_driver'           => 'nullable|in:disabled,ntfy,android_gw,free_mobile,webhook',
                'sms_ntfy_url'         => 'nullable|url|max:255',
                'sms_ntfy_token'       => 'nullable|string|max:200',
                'sms_android_url'      => 'nullable|url|max:255',
                'sms_android_login'    => 'nullable|string|max:50',
                'sms_android_pass'     => 'nullable|string|max:100',
                'sms_free_mobile_user' => 'nullable|string|max:50',
                'sms_free_mobile_key'  => 'nullable|string|max:100',
                'sms_webhook_url'      => 'nullable|url|max:500',
                'sms_webhook_method'   => 'nullable|in:GET,POST',
                'sms_webhook_body'     => 'nullable|string|max:500',
                'clear_secrets'        => 'nullable|array',
                'clear_secrets.*'      => Rule::in(SettingService::SECRET_KEYS),
            ]);
            $this->storeSettings($data);
        }

        ActivityLogger::log('Paramètres modifiés', 'Sécurité');
        return back()->with('success', 'Paramètres de sécurité enregistrés.');
    }

    /**
     * Enregistre les reglages valides. Un secret laisse vide est conserve ; il n'est efface que s'il est coche dans clear_secrets.
     */
    private function storeSettings(array $data): void
    {
        $clear = $data['clear_secrets'] ?? [];
        unset($data['clear_secrets']);

        foreach ($data as $key => $value) {
            if (SettingService::isSecret($key)) {
                if (in_array($key, $clear, true)) {
                    $this->settings->set($key, '');
                    continue;
                }
                if ($value === null || $value === '') {
                    continue;
                }
            }
            $this->settings->set($key, $value ?? '');
        }
    }

    // ---------------------------------------------------------------
    // Mail
    // ---------------------------------------------------------------
    public function mail()
    {
        return view('admin.settings.mail', ['s' => $this->settings->all(), 'secrets' => $this->settings->secretsStatus()]);
    }

    public function updateMail(Request $request)
    {
        $data = $request->validate([
            'mail_driver'          => 'required|in:disabled,sendmail,smtp',
            'mail_from_address'    => 'nullable|email|max:255',
            'mail_smtp_host'       => 'nullable|string|max:255',
            'mail_smtp_port'       => 'nullable|integer|min:1|max:65535',
            'mail_smtp_encryption' => 'nullable|in:tls,ssl,starttls',
            'mail_smtp_username'   => 'nullable|string|max:255',
            'mail_smtp_password'   => 'nullable|string|max:255',
            'notify_account_deletion' => 'nullable|in:0,1',
            'clear_secrets'        => 'nullable|array',
            'clear_secrets.*'      => Rule::in(SettingService::SECRET_KEYS),
        ]);
        $this->storeSettings($data);
        ActivityLogger::log('Paramètres modifiés', 'E-mail');
        return back()->with('success', 'Paramètres e-mail enregistrés.');
    }

    public function testMail(Request $request)
    {
        $request->validate(['test_email' => 'required|email']);
        try {
            $this->applyMailConfig();
            Mail::to($request->test_email)->send(new TestMail());
            return back()->with('success', 'E-mail de test envoyé à ' . $request->test_email . '.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Échec d\'envoi : ' . $e->getMessage());
        }
    }

    // ---------------------------------------------------------------
    // Maintenance
    // ---------------------------------------------------------------
    public function maintenance()
    {
        return view('admin.settings.maintenance', ['s' => $this->settings->all()]);
    }

    public function updateMaintenance(Request $request)
    {
        $this->settings->set('maintenance_mode', $request->boolean('maintenance_mode') ? '1' : '0');
        $this->settings->set('maintenance_message', $request->input('maintenance_message', ''));
        ActivityLogger::log('Paramètres modifiés', 'Maintenance');
        return back()->with('success', 'Paramètres de maintenance enregistrés.');
    }

    // ---------------------------------------------------------------
    // Reservations
    // ---------------------------------------------------------------
    public function reservations()
    {
        $roles = Role::orderBy('id')->get();
        return view('admin.settings.reservations', ['roles' => $roles]);
    }

    public function updateReservations(Request $request)
    {
        $roles = Role::all();
        foreach ($roles as $role) {
            $role->update([
                'max_reservations_day'  => (int) ($request->input("roles.{$role->id}.max_reservations_day", -1)),
                'max_reservations_week' => (int) ($request->input("roles.{$role->id}.max_reservations_week", -1)),
                'cancel_deadline_minutes' => (int) ($request->input("roles.{$role->id}.cancel_deadline_minutes", -1)),
                'advance_days'          => (int) ($request->input("roles.{$role->id}.advance_days", 30)),
            ]);
        }
        ActivityLogger::log('Paramètres modifiés', 'Réservations');
        return back()->with('success', 'Limites par rôle enregistrées.');
    }

    // ---------------------------------------------------------------
    // Internal
    // ---------------------------------------------------------------
    private function applyMailConfig(): void
    {
        $driver = $this->settings->get('mail_driver', 'log');
        if ($driver === 'smtp') {
            config([
                'mail.default'                    => 'smtp',
                'mail.mailers.smtp.host'          => $this->settings->get('mail_smtp_host', '127.0.0.1'),
                'mail.mailers.smtp.port'          => (int) $this->settings->get('mail_smtp_port', 587),
                'mail.mailers.smtp.encryption'    => $this->settings->get('mail_smtp_encryption', 'tls'),
                'mail.mailers.smtp.username'      => $this->settings->get('mail_smtp_username'),
                'mail.mailers.smtp.password'      => $this->settings->get('mail_smtp_password'),
            ]);
        } elseif ($driver === 'sendmail') {
            config(['mail.default' => 'sendmail']);
        } else {
            config(['mail.default' => 'log']);
        }
        config([
            'mail.from.address' => $this->settings->get('mail_from_address', 'noreply@example.com'),
            'mail.from.name'    => $this->settings->get('app_name', 'NovaCreneaux'),
        ]);
    }
}
