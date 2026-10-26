<?php

namespace Tests\Feature;

use App\Models\NavItem;
use App\Models\Role;
use App\Models\User;
use App\Support\CanonicalUrl;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private function navPayload(array $override = []): array
    {
        return $override + ['label' => 'Lien', 'type' => 'external', 'url' => 'https://exemple.fr', 'target' => '_self', 'is_active' => '1'];
    }

    // --- Liens du menu : jamais de javascript: ---

    public function test_navigation_links_only_accept_safe_schemes(): void
    {
        $staff = $this->staff([Permission::NAVIGATION]);
        $this->actingAs($staff);
        $before = NavItem::count(); // le menu par defaut contient deja des entrees

        foreach (['javascript:alert(1)', 'JaVaScRiPt:alert(1)', ' javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', 'vbscript:x', '//evil.example/x', 'evil.example', "https://a.b/x y"] as $bad) {
            $this->post(route('admin.nav.store'), $this->navPayload(['url' => $bad]))->assertSessionHasErrors('url');
        }
        $this->assertSame($before, NavItem::count());

        foreach (['https://exemple.fr/page?x=1', 'http://exemple.fr', 'mailto:cdi@exemple.fr', 'tel:+33123456789', '/p/reglement', '#haut'] as $good) {
            $this->post(route('admin.nav.store'), $this->navPayload(['url' => $good]))->assertSessionHasNoErrors();
        }
        $this->assertSame($before + 6, NavItem::count());
    }

    public function test_a_dangerous_link_stored_before_the_check_is_neutralised_when_displayed(): void
    {
        $item = new NavItem(['label' => 'Ancien', 'type' => 'external', 'url' => 'javascript:alert(1)']);

        $this->assertSame('#', $item->resolvedUrl());
        $this->assertSame('https://ok.example/a', (new NavItem(['type' => 'external', 'url' => 'https://ok.example/a']))->resolvedUrl());
    }

    // --- Textes places dans du JavaScript (confirm) ---

    public function test_names_and_labels_cannot_break_out_of_a_confirm_dialog(): void
    {
        $evil = "');alert(document.cookie);//";
        $nav = NavItem::create(['label' => $evil, 'type' => 'external', 'url' => 'https://ok.example', 'target' => '_self', 'icon' => 'bi-link', 'position' => 1]);
        $role = Role::create(['name' => 'pirate', 'label' => $evil, 'color' => '#3b7ddd']);
        $pupil = User::factory()->create(['name' => $evil, 'role_id' => Role::defaultUser()->id]);
        $this->actingAs($this->admin());

        foreach ([route('admin.nav.index'), route('admin.nav.edit', $nav), route('admin.roles.index'), route('admin.roles.edit', $role), route('admin.users.edit', $pupil)] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            preg_match_all('/onclick="return (confirm\([^"]*\))"/', $html, $dialogs);
            $this->assertNotEmpty($dialogs[1], $url);
            foreach ($dialogs[1] as $call) {
                // Apres lecture de l'attribut par le navigateur : une seule chaine entre apostrophes, la valeur n'en contient pas
                $decoded = html_entity_decode($call, ENT_QUOTES);
                $this->assertSame(2, substr_count($decoded, "'"), "{$url} : {$decoded}");
                $this->assertStringNotContainsString("');alert(", $decoded, $url);
            }
        }
    }

    // --- Logo ---

    private function realUpload(string $name, string $content, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'nc-logo-');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, $mime, null, true);
    }

    private function cleanLogos(): void
    {
        foreach (glob(public_path('uploads/logo/*')) ?: [] as $file) {
            @unlink($file);
        }
    }

    public function test_the_logo_extension_comes_from_the_content_and_the_name_is_random(): void
    {
        $this->actingAs($this->admin());
        $gif = base64_decode('R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==');

        // Nom annonce par le navigateur : "jpeg". Contenu reel : GIF. Le fichier enregistre suit le contenu.
        $this->post(route('admin.settings.logo.upload'), ['logo' => $this->realUpload('photo.jpeg', $gif, 'image/jpeg')])->assertSessionHasNoErrors();

        $stored = setting('site_logo');
        $this->assertMatchesRegularExpression('#^uploads/logo/logo_[a-f0-9]{16}\.gif$#', $stored);
        $this->assertSame([], glob(public_path('uploads/logo/*.php')) ?: []);
        $this->cleanLogos();
    }

    public function test_non_images_are_refused_as_logo_whatever_their_name_or_declared_type(): void
    {
        $this->actingAs($this->admin());

        foreach ([
            $this->realUpload('logo.png', '<?php system($_GET["c"]); ?>', 'image/png'),
            $this->realUpload('logo.php', base64_decode('R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=='), 'image/gif'),
            $this->realUpload('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>', 'image/svg+xml'),
        ] as $file) {
            $this->post(route('admin.settings.logo.upload'), ['logo' => $file])->assertSessionHasErrors('logo');
            $this->assertSame([], glob(public_path('uploads/logo/*.php')) ?: []);
            $this->assertSame([], glob(public_path('uploads/logo/*.svg')) ?: []);
        }
        $this->cleanLogos();
    }

    public function test_a_gif_with_php_code_appended_is_stored_as_a_harmless_gif_with_a_random_name(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.settings.logo.upload'), ['logo' => $this->realUpload('logo.jpg', "GIF89a\x01\x00\x01\x00\x00\x00\x00;<?php system(\$_GET['c']); ?>", 'image/jpeg')]);

        $stored = (string) setting('site_logo');
        $this->assertTrue($stored === '' || preg_match('#^uploads/logo/logo_[a-f0-9]{16}\.gif$#', $stored) === 1, $stored);
        $this->assertSame([], glob(public_path('uploads/logo/*.php')) ?: []);
        $this->cleanLogos();
    }

    public function test_only_files_of_the_logo_folder_are_ever_deleted(): void
    {
        $victim = storage_path('app/victim-test.txt');
        file_put_contents($victim, 'x');
        app(\App\Services\SettingService::class)->set('site_logo', '../storage/app/victim-test.txt');

        $this->actingAs($this->admin())->delete(route('admin.settings.logo.delete'));

        $this->assertFileExists($victim);
        @unlink($victim);
    }

    // --- En-tetes ---

    public function test_responses_carry_security_headers(): void
    {
        $response = $this->get(route('login'));

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("object-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("base-uri 'self'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_plain_http_responses_do_not_carry_hsts(): void
    {
        $this->get('http://cdi.example.fr/login')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_https_responses_also_ask_browsers_to_stay_on_https(): void
    {
        $this->get('https://cdi.example.fr/login')->assertHeader('Strict-Transport-Security');
    }

    // --- Reinitialisation du mot de passe ---

    public function test_the_reset_form_answers_the_same_whether_or_not_the_account_exists(): void
    {
        Mail::fake();
        $known = $this->student();

        $a = $this->post(route('password.email'), ['email' => $known->email]);
        $b = $this->post(route('password.email'), ['email' => 'inconnu@example.com']);

        $a->assertSessionHasNoErrors();
        $b->assertSessionHasNoErrors();
        $this->assertSame(session('status'), $a->getSession()->get('status'));
        $this->assertStringContainsString('Si cette adresse correspond', $b->getSession()->get('status'));
        $this->assertSame($a->getSession()->get('status'), $b->getSession()->get('status'));
    }

    // --- Sessions ---

    public function test_changing_the_password_ends_the_other_sessions_of_the_account(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Ancien-Mot-De-Passe-1'), 'role_id' => Role::defaultUser()->id]);
        $this->post(route('login'), ['email' => $user->email, 'password' => 'Ancien-Mot-De-Passe-1'])->assertRedirect();
        $this->get(route('notifications.index'))->assertOk();

        $user->forceFill(['password' => bcrypt('Nouveau-Mot-De-Passe-2')])->save();
        $this->app['auth']->forgetGuards(); // un vrai visiteur a une requete neuve : le test garde sinon l'utilisateur deja charge

        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    // --- Envoi de SMS et inscriptions ---

    public function test_a_code_by_sms_can_only_be_requested_once_a_minute(): void
    {
        $user = $this->student();

        $this->actingAs($user)->post(route('mfa.sms.setup.post'), ['contact' => '0612345678']);
        $second = $this->post(route('mfa.sms.setup.post'), ['contact' => '0612345678']);

        $second->assertSessionHasErrors('contact');
        $this->assertStringContainsString('Attendez 60 secondes', session('errors')->first('contact'));
    }

    public function test_the_sms_setup_endpoint_is_rate_limited(): void
    {
        $user = $this->student();
        $statuses = [];
        for ($i = 0; $i < 7; $i++) {
            $statuses[] = $this->actingAs($user)->post(route('mfa.sms.setup.post'), ['contact' => '06123456' . $i . '0'])->getStatusCode();
        }

        $this->assertContains(429, $statuses);
    }

    public function test_signup_attempts_are_rate_limited(): void
    {
        $statuses = [];
        for ($i = 0; $i < 12; $i++) {
            $statuses[] = $this->post(route('register'), ['name' => 'x', 'email' => "x{$i}@example.com", 'password' => 'abc'])->getStatusCode();
        }

        $this->assertSame(429, $statuses[11]);
        $this->assertNotContains(429, array_slice($statuses, 0, 10));
    }

    // --- Adresse du site dans les liens generes ---

    public function test_generated_links_use_the_configured_site_address_not_the_host_header(): void
    {
        $this->assertTrue(CanonicalUrl::apply('https://cdi.example.fr', true));

        $this->assertSame('https://cdi.example.fr/reset-password/abc', url('/reset-password/abc'));
        $this->assertSame('https://cdi.example.fr/login', route('login'));
        $this->assertTrue(config('session.secure'));
        URL::forceRootUrl(null);
        URL::forceScheme(null);
    }

    public function test_nothing_is_forced_for_unconfigured_sites_development_or_the_console(): void
    {
        $this->assertFalse(CanonicalUrl::apply('https://votre-domaine.fr', true));
        $this->assertFalse(CanonicalUrl::apply('http://localhost:8000', true));
        $this->assertFalse(CanonicalUrl::apply('', true));
        $this->assertFalse(CanonicalUrl::apply(null, true));
        $this->assertFalse(CanonicalUrl::apply('https://cdi.example.fr', false));
        $this->assertNull(config('session.secure'));
    }

    public function test_an_explicit_session_secure_setting_is_never_overridden(): void
    {
        config(['session.secure' => false]);

        CanonicalUrl::apply('https://cdi.example.fr', true);

        $this->assertFalse(config('session.secure'));
        URL::forceRootUrl(null);
        URL::forceScheme(null);
    }
}
