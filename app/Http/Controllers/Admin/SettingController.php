<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use App\Models\Role;
use App\Rules\TrustedProxyList;
use App\Services\ActivityLogger;
use App\Support\ActivityChanges;
use App\Support\Customization;
use App\Support\HtmlSanitizer;
use App\Support\ReservationLimits;
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
        $before = $this->settings->snapshot();
        $data = $request->validate([
            'app_name'         => 'required|string|max:100',
            'site_url'         => 'nullable|url|max:255',
            'site_description' => 'nullable|string',
            'site_keywords'    => 'nullable|string|max:500',
            'timezone'         => 'nullable|string|max:100',
            'locale'           => 'nullable|in:fr,en',
        ] + Customization::rules());
        foreach ($data as $key => $value) $this->settings->set($key, $value ?? '');
        $this->logSettings('Général', $before);
        return back()->with('success', 'Paramètres généraux enregistrés.');
    }

    public function uploadLogo(Request $request)
    {
        $request->validate(['logo' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:2048']);
        $file = $request->file('logo');
        // L'extension vient du contenu du fichier, jamais du nom envoye par le navigateur : un "logo.php" valide comme image resterait un script
        $extension = $file->guessExtension();
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return back()->withErrors(['logo' => "Ce fichier n'est pas une image acceptée (JPG, PNG, GIF ou WebP)."]);
        }
        $dir = public_path('uploads/logo');
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $this->removeLogoFile();
        $filename = 'logo_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $file->move($dir, $filename);
        $this->settings->set('site_logo', 'uploads/logo/' . $filename);
        return back()->with('success', 'Logo mis à jour.');
    }

    /** Supprime l'ancien logo, et seulement un fichier du dossier des logos. */
    private function removeLogoFile(): void
    {
        $old = (string) $this->settings->get('site_logo');
        if (str_starts_with($old, 'uploads/logo/') && !str_contains($old, '..') && is_file(public_path($old))) {
            @unlink(public_path($old));
        }
    }

    public function deleteLogo()
    {
        $this->removeLogoFile();
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
        $this->settings->set('home_message', HtmlSanitizer::clean($data['home_message'] ?? ''));
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
        $before = $this->settings->snapshot();
        $request->validate([
            'registration_terms'          => 'nullable|string',
            'registration_open'           => 'boolean',
            'allow_name_change'           => 'boolean',
            'allow_account_deletion'      => 'boolean',
            'allowed_email_domain'        => 'nullable|array',
            'allowed_email_domain.*'      => 'nullable|string|max:100|regex:/^[a-zA-Z0-9\-\.]+$/',
            'password_min_length'         => ['nullable', 'integer', 'min:' . Customization::PASSWORD_MIN_LENGTH['min'], 'max:' . Customization::PASSWORD_MIN_LENGTH['max']],
        ]);
        if ($request->filled('password_min_length')) {
            $this->settings->set('password_min_length', (string) $request->integer('password_min_length'));
        }

        // Consolider les domaines en CSV
        $domains = array_values(array_filter(array_map(
            'trim',
            $request->input('allowed_email_domain', [])
        )));
        $this->settings->set('allowed_email_domain', implode(',', $domains));

        $this->settings->set('registration_terms',     HtmlSanitizer::clean($request->input('registration_terms', '')));
        $this->settings->set('registration_open',      $request->boolean('registration_open') ? '1' : '0');
        $this->settings->set('allow_name_change',      $request->boolean('allow_name_change') ? '1' : '0');
        $this->settings->set('allow_account_deletion', $request->boolean('allow_account_deletion') ? '1' : '0');

        $this->logSettings('Authentification', $before);
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
        $before = $this->settings->snapshot();
        $data = $request->validate(['trusted_proxies' => ['nullable', 'string', 'max:255', new TrustedProxyList]]);

        $this->settings->set('trusted_proxies', implode(',', TrustedProxyList::parse((string) ($data['trusted_proxies'] ?? ''))));
        $this->logSettings('Proxys de confiance', $before);
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
        $before = $this->settings->snapshot();
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

        $this->logSettings('Sécurité', $before);
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
        $before = $this->settings->snapshot();
        $data = $request->validate([
            'mail_driver'          => 'required|in:disabled,sendmail,smtp',
            'mail_from_address'    => 'nullable|email|max:255',
            'mail_smtp_host'       => 'nullable|string|max:255',
            'mail_smtp_port'       => 'nullable|integer|min:1|max:65535',
            'mail_smtp_encryption' => 'nullable|in:tls,ssl,starttls',
            'mail_smtp_username'   => 'nullable|string|max:255',
            'mail_smtp_password'   => 'nullable|string|max:255',
            'notify_account_deletion' => 'nullable|in:0,1',
            'mail_use_site_color'  => 'nullable|in:0,1',
            'mail_color'           => ['exclude_unless:mail_use_site_color,0', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'mail_signature'       => 'nullable|string|max:500',
            'clear_secrets'        => 'nullable|array',
            'clear_secrets.*'      => Rule::in(SettingService::SECRET_KEYS),
        ]);
        // Couleur vide = celle du site (Customization::mailColor) ; sans le choix dans le formulaire, rien ne change
        if ($request->has('mail_use_site_color')) {
            $data['mail_color'] = $request->boolean('mail_use_site_color') ? '' : $data['mail_color'];
        }
        unset($data['mail_use_site_color']);
        $this->storeSettings($data);
        $this->logSettings('E-mail', $before);
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
        $before = $this->settings->snapshot();
        $this->settings->set('maintenance_mode', $request->boolean('maintenance_mode') ? '1' : '0');
        $this->settings->set('maintenance_message', $request->input('maintenance_message', ''));
        $this->logSettings('Maintenance', $before);
        return back()->with('success', 'Paramètres de maintenance enregistrés.');
    }

    // ---------------------------------------------------------------
    // Reservations
    // ---------------------------------------------------------------
    public function reservations()
    {
        return view('admin.settings.reservations', [
            'roles' => Role::withCount('users')->orderBy('id')->get(),
            'fields' => ReservationLimits::fields(),
        ]);
    }

    public function updateReservations(Request $request)
    {
        $input = $request->validate(ReservationLimits::rules(), [], ReservationLimits::attributes())['roles'];

        // Un role absent du formulaire (cree entre l'affichage et l'envoi) garde ses limites
        foreach (Role::whereKey(array_keys($input))->get() as $role) {
            $role->update(ReservationLimits::fromInput($input[$role->id]));
        }
        ActivityLogger::log('Paramètres modifiés', 'Réservations');
        return back()->with('success', 'Limites par rôle enregistrées.');
    }

    // ---------------------------------------------------------------
    // Internal
    // ---------------------------------------------------------------
    /** Journal : "Parametres modifies" avec l'avant / apres de chaque reglage change (secrets masques). */
    private function logSettings(string $section, array $before): void
    {
        $changes = ActivityChanges::diff($before, $this->settings->snapshot(), SettingService::SECRET_KEYS);
        ActivityLogger::log('Paramètres modifiés', $section, $changes);
    }

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
