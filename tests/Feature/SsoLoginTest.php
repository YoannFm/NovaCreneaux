<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Services\SettingService;
use App\Support\SsoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Connexion avec le compte de l'etablissement : OpenID Connect et CAS, desactivee par defaut. */
class SsoLoginTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private const ISSUER = 'https://connexion.exemple.fr/realms/lycee';
    private const CAS = 'https://cas.exemple.fr/cas';

    /** Nonce de la tentative en cours, que le faux fournisseur recopie dans le jeton d'identite. */
    private string $nonce = '';

    private function settings(array $values): void
    {
        foreach ($values as $key => $value) {
            app(SettingService::class)->set($key, $value);
        }
        Cache::flush();
    }

    private function enableOidc(): void
    {
        $this->settings(['sso_driver' => 'oidc', 'oidc_issuer' => self::ISSUER, 'oidc_client_id' => 'novacreneaux', 'oidc_client_secret' => 'secret-du-client']);
    }

    private static function jwt(array $claims): string
    {
        $part = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        return $part(['alg' => 'RS256']) . '.' . $part($claims) . '.signature';
    }

    /** Fournisseur OpenID Connect factice ; $claims ecrase les champs du jeton d'identite. */
    private function fakeOidc(array $claims = [], array $userinfo = []): void
    {
        Http::fake([
            self::ISSUER . '/.well-known/openid-configuration' => Http::response([
                'issuer' => self::ISSUER,
                'authorization_endpoint' => self::ISSUER . '/auth',
                'token_endpoint' => self::ISSUER . '/token',
                'userinfo_endpoint' => self::ISSUER . '/userinfo',
            ]),
            self::ISSUER . '/token' => function (HttpRequest $request) use ($claims) {
                return Http::response(['access_token' => 'jeton-acces', 'id_token' => self::jwt($claims + [
                    'iss' => self::ISSUER, 'aud' => 'novacreneaux', 'nonce' => $this->nonce, 'exp' => time() + 300,
                    'email' => 'Lea.Martin@Exemple.fr', 'name' => 'Léa Martin',
                ])]);
            },
            self::ISSUER . '/userinfo' => Http::response($userinfo),
        ]);
    }

    /** Va jusqu'au service puis revient avec un code. */
    private function oidcRoundTrip(): \Illuminate\Testing\TestResponse
    {
        $location = $this->get(route('sso.redirect'))->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->nonce = $query['nonce'];

        return $this->get(route('sso.callback', ['state' => $query['state'], 'code' => 'code-recu']));
    }

    public function test_it_is_disabled_by_default_and_password_login_is_unchanged(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee(SsoSettings::DEFAULT_LABEL)->assertSee('Mot de passe');
        $this->get(route('sso.redirect'))->assertRedirect(route('login'))->assertSessionHasErrors('email');
    }

    public function test_oidc_redirects_with_state_nonce_and_pkce(): void
    {
        $this->enableOidc();
        $this->fakeOidc();
        $this->get(route('login'))->assertSee(SsoSettings::DEFAULT_LABEL);

        $location = $this->get(route('sso.redirect'))->headers->get('Location');

        $this->assertStringStartsWith(self::ISSUER . '/auth?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('novacreneaux', $query['client_id']);
        $this->assertSame(route('sso.callback'), $query['redirect_uri']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame(session('sso_attempt.state'), $query['state']);
        $this->assertSame(session('sso_attempt.nonce'), $query['nonce']);
    }

    public function test_oidc_logs_in_the_existing_account_by_email(): void
    {
        $this->enableOidc();
        $this->fakeOidc();
        $user = $this->student(['email' => 'lea.martin@exemple.fr']);

        $this->oidcRoundTrip()->assertRedirect(route('reservations.index'));

        $this->assertAuthenticatedAs($user);
        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::ISSUER . '/token'
            && $request['code_verifier'] !== null && $request->hasHeader('Authorization', 'Basic ' . base64_encode('novacreneaux:secret-du-client')));
        $this->assertTrue(ActivityLog::where('action', 'Connexion')->where('user_id', $user->id)->exists());
    }

    public function test_an_unknown_address_is_refused_unless_auto_creation_is_on(): void
    {
        $this->enableOidc();
        $this->fakeOidc();

        $this->oidcRoundTrip()->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(0, User::where('email', 'lea.martin@exemple.fr')->count());

        $this->settings(['sso_auto_create' => '1']);
        $this->oidcRoundTrip()->assertRedirect(route('reservations.index'));
        $user = User::where('email', 'lea.martin@exemple.fr')->firstOrFail();
        $this->assertSame('Léa Martin', $user->name);
        $this->assertSame(Role::defaultUser()->id, $user->role_id);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_auto_creation_respects_the_allowed_domains(): void
    {
        $this->enableOidc();
        $this->fakeOidc();
        $this->settings(['sso_auto_create' => '1', 'allowed_email_domain' => 'lycee.exemple.fr']);

        $this->oidcRoundTrip()->assertSessionHasErrors('email');

        $this->assertSame(0, User::where('email', 'lea.martin@exemple.fr')->count());
    }

    public function test_a_token_for_another_attempt_or_application_is_refused(): void
    {
        $this->enableOidc();
        $this->student(['email' => 'lea.martin@exemple.fr']);

        foreach ([['nonce' => 'autre'], ['aud' => 'autre-application'], ['iss' => 'https://pirate.exemple.fr'], ['exp' => time() - 3600], ['email_verified' => false]] as $claims) {
            $this->fakeOidc($claims);
            $this->oidcRoundTrip()->assertRedirect(route('login'))->assertSessionHasErrors('email');
            $this->assertGuest();
        }
    }

    public function test_a_callback_without_the_attempt_state_is_refused(): void
    {
        $this->enableOidc();
        $this->fakeOidc();
        $this->student(['email' => 'lea.martin@exemple.fr']);
        $this->get(route('sso.redirect'));

        $this->get(route('sso.callback', ['state' => 'pas-le-bon', 'code' => 'x']))->assertSessionHasErrors('email');

        $this->assertGuest();
        Http::assertNotSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/token'));
    }

    public function test_the_email_can_come_from_userinfo(): void
    {
        $this->enableOidc();
        $this->fakeOidc(['email' => null], ['email' => 'lea.martin@exemple.fr']);
        $user = $this->student(['email' => 'lea.martin@exemple.fr']);

        $this->oidcRoundTrip();

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_account_with_two_factor_goes_through_the_challenge_and_a_disabled_one_is_refused(): void
    {
        $this->enableOidc();
        $this->fakeOidc();
        $user = $this->student(['email' => 'lea.martin@exemple.fr', 'mfa_email_enabled' => true]);

        $this->oidcRoundTrip()->assertRedirect(route('mfa.challenge.form'));
        $this->assertGuest();
        $this->assertSame($user->id, session('mfa_user_id'));

        $user->update(['is_active' => false, 'mfa_email_enabled' => false]);
        $this->oidcRoundTrip()->assertSessionHasErrors(['email' => 'Votre compte est désactivé.']);
        $this->assertGuest();
    }

    public function test_cas_validates_the_ticket_for_this_attempt_and_reads_the_mail_attribute(): void
    {
        $this->settings(['sso_driver' => 'cas', 'cas_url' => self::CAS]);
        $user = $this->student(['email' => 'lea.martin@exemple.fr']);
        Http::fake([self::CAS . '/p3/serviceValidate*' => Http::response(<<<'XML'
            <cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas">
              <cas:authenticationSuccess>
                <cas:user>lmartin</cas:user>
                <cas:attributes><cas:mail>Lea.Martin@exemple.fr</cas:mail><cas:displayName>Léa Martin</cas:displayName></cas:attributes>
              </cas:authenticationSuccess>
            </cas:serviceResponse>
            XML)]);

        $location = $this->get(route('sso.redirect'))->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertStringStartsWith(self::CAS . '/login?', $location);
        $this->assertSame(route('sso.callback', ['state' => session('sso_attempt.state')]), $query['service']);

        $this->get($query['service'] . '&ticket=ST-123')->assertRedirect(route('reservations.index'));

        $this->assertAuthenticatedAs($user);
        Http::assertSent(fn (HttpRequest $request) => $request['ticket'] === 'ST-123' && $request['service'] === $query['service']);
    }

    public function test_a_refused_cas_ticket_does_not_log_in(): void
    {
        $this->settings(['sso_driver' => 'cas', 'cas_url' => self::CAS]);
        $this->student(['email' => 'lea.martin@exemple.fr']);
        Http::fake([self::CAS . '/*' => Http::response('<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas"><cas:authenticationFailure code="INVALID_TICKET">Ticket inconnu</cas:authenticationFailure></cas:serviceResponse>')]);
        $this->get(route('sso.redirect'));

        $this->get(route('sso.callback', ['state' => session('sso_attempt.state'), 'ticket' => 'ST-faux']))->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_admin_page_saves_the_settings_and_never_shows_the_secret(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.settings.sso.update'), ['sso_driver' => 'oidc'])->assertSessionHasErrors(['oidc_issuer', 'oidc_client_id']);
        $this->post(route('admin.settings.sso.update'), ['sso_driver' => 'oidc', 'oidc_issuer' => 'http://non-chiffre.exemple.fr'])->assertSessionHasErrors('oidc_issuer');

        $this->post(route('admin.settings.sso.update'), [
            'sso_driver' => 'oidc', 'oidc_issuer' => self::ISSUER, 'oidc_client_id' => 'novacreneaux', 'oidc_client_secret' => 'secret-du-client',
        ])->assertSessionHasNoErrors();

        $this->assertSame(SsoSettings::OIDC, SsoSettings::driver());
        $this->assertSame('secret-du-client', setting('oidc_client_secret'));
        $this->get(route('admin.settings.sso'))->assertOk()->assertDontSee('secret-du-client')->assertSee(route('sso.callback'), false);
        $this->assertStringNotContainsString('secret-du-client', json_encode(ActivityLog::pluck('changes')));
    }
}
