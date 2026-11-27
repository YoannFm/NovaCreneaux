<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\MfaCode;
use App\Models\User;
use App\Services\SettingService;
use App\Services\TotpService;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/**
 * Ajout d'une methode de double authentification : une session restee ouverte ne doit pas suffire a installer sa
 * propre methode, les codes de confirmation ne s'essaient pas sans fin, et chaque changement est journalise.
 */
class MfaSetupProtectionTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private const SECRET = 'JBSWY3DPEHPK3PXP';
    private const PASSWORD = 'Mot-De-Passe-1';

    private function confirmed(): array
    {
        return ['auth.password_confirmed_at' => time()];
    }

    private function totpCode(string $secret): string
    {
        $generate = new \ReflectionMethod(TotpService::class, 'generateCode');

        return $generate->invoke(app(TotpService::class), $secret, intdiv(time(), 30));
    }

    private function userWithTotp(array $attributes = []): User
    {
        return $this->student($attributes + ['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => json_encode([hash('sha256', 'aaaaa-bbbbb')])]);
    }

    public function test_adding_an_application_a_phone_or_a_key_asks_for_the_password(): void
    {
        $this->actingAs($this->student());

        foreach (['mfa.totp.enable.form', 'mfa.enable.form', 'mfa.sms.setup', 'mfa.webauthn.add'] as $route) {
            $this->get(route($route))->assertRedirect(route('password.confirm'));
        }
        $this->getJson(route('mfa.webauthn.options'))->assertStatus(423);

        $this->withSession($this->confirmed())->get(route('mfa.totp.enable.form'))->assertOk();
    }

    public function test_staff_required_to_use_two_factor_can_confirm_the_password_to_add_a_method(): void
    {
        app(SettingService::class)->set('require_2fa_admin', '1');
        $this->actingAs($this->staff([Permission::PAGES]));

        $this->get(route('mfa.totp.enable.form'))->assertRedirect(route('password.confirm'));
        $this->get(route('password.confirm'))->assertOk();
        $this->post(route('password.confirm'), ['password' => 'password'])->assertRedirect(route('mfa.totp.enable.form'));
        $this->get(route('mfa.totp.enable.form'))->assertOk();
    }

    public function test_the_confirmation_page_explains_what_to_do_without_a_password_only_with_the_school_sign_in(): void
    {
        $this->actingAs($this->student());
        $this->get(route('password.confirm'))->assertDontSee('Mot de passe oublié');

        app(SettingService::class)->set('sso_driver', 'cas');
        app(SettingService::class)->set('cas_url', 'https://cas.exemple.fr/cas');
        $this->get(route('password.confirm'))->assertSee('Mot de passe oublié')->assertSee(route('logout'), false);
    }

    public function test_the_current_password_cannot_be_tried_endlessly(): void
    {
        $this->actingAs($this->userWithTotp());
        $wrong = ['password' => 'faux'];

        $statuses = collect([
            fn () => $this->delete(route('mfa.totp.disable'), $wrong),
            fn () => $this->delete(route('mfa.email.disable'), $wrong),
            fn () => $this->post(route('mfa.recovery-codes.regenerate'), $wrong),
            fn () => $this->delete(route('profile.destroy'), $wrong),
            fn () => $this->patch(route('profile.update'), ['name' => 'Léa', 'email' => 'autre@exemple.fr', 'current_password' => 'faux']),
            fn () => $this->post(route('password.confirm'), $wrong),
            fn () => $this->delete(route('mfa.sms.disable'), $wrong),
        ])->map(fn (callable $call) => $call()->getStatusCode());

        // Un seul compteur pour toutes les verifications du mot de passe : le 7e essai, quelle que soit la page, est refuse
        $this->assertNotContains(429, $statuses->take(6)->all());
        $this->assertSame(429, $statuses->last());
    }

    public function test_each_limit_counts_its_own_requests(): void
    {
        Mail::fake();
        $this->actingAs($this->staff([Permission::RESERVATIONS]));
        for ($i = 0; $i < 7; $i++) {
            $this->getJson(route('admin.reservations.users', ['q' => 'a']))->assertOk();
        }

        // Avant, toutes les limites sans nom partageaient le compteur du compte : ce code etait refuse (429)
        $this->post(route('mfa.email.enable'))->assertRedirect(route('mfa.email.confirm'));
    }

    public function test_sms_service_addresses_must_be_web_addresses(): void
    {
        $this->actingAs($this->admin());

        foreach (['sms_ntfy_url', 'sms_android_url', 'sms_webhook_url'] as $field) {
            $this->post(route('admin.settings.security.update'), ['_sms_save' => '1', $field => 'file:///etc/passwd'])->assertSessionHasErrors($field);
        }
        $this->post(route('admin.settings.security.update'), ['_sms_save' => '1', 'sms_android_url' => 'http://192.168.1.20:8080'])->assertSessionHasNoErrors();
    }

    public function test_an_active_application_cannot_be_replaced_without_disabling_it_first(): void
    {
        $user = $this->userWithTotp();
        $this->actingAs($user)->withSession($this->confirmed());

        $this->get(route('mfa.totp.enable.form'))->assertRedirect(route('profile.edit'))->assertSessionHas('error');
        $this->withSession(['mfa_totp_setup_secret' => 'KRSXG5CTMVRXEZLU'])
            ->post(route('mfa.totp.enable'), ['code' => $this->totpCode('KRSXG5CTMVRXEZLU')])->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame(self::SECRET, $user->two_factor_secret);
        $this->assertStringContainsString(hash('sha256', 'aaaaa-bbbbb'), $user->two_factor_recovery_codes);
    }

    public function test_confirmation_codes_cannot_be_tried_endlessly(): void
    {
        Mail::fake();
        $this->actingAs($this->student());
        $this->post(route('mfa.email.enable'));

        $statuses = collect(range(1, 7))->map(fn (int $i) => $this->post(route('mfa.email.confirm.post'), ['code' => sprintf('%06d', $i)])->getStatusCode());

        $this->assertSame(429, $statuses->last());
    }

    public function test_an_account_disabled_during_the_challenge_is_not_signed_in(): void
    {
        $user = $this->userWithTotp(['is_active' => false]);

        $this->withSession(['mfa_user_id' => $user->id])
            ->post(route('mfa.challenge'), ['method' => 'totp', 'code' => $this->totpCode(self::SECRET)])
            ->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_recovery_code_is_not_accepted_when_the_list_changed_meanwhile(): void
    {
        $user = $this->userWithTotp();
        // Un autre envoi du meme code vient de le consommer : la liste lue n'est plus celle de la base
        User::whereKey($user->id)->update(['two_factor_recovery_codes' => json_encode([null])]);

        $this->withSession(['mfa_user_id' => $user->id])
            ->post(route('mfa.challenge.recovery'), ['code' => 'aaaaa-bbbbb'])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_a_recovery_code_signs_in_once_only(): void
    {
        $user = $this->userWithTotp();

        $this->withSession(['mfa_user_id' => $user->id])
            ->post(route('mfa.challenge.recovery'), ['code' => 'aaaaa-bbbbb'])->assertRedirect(route('reservations.index'));
        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $this->withSession(['mfa_user_id' => $user->id])
            ->post(route('mfa.challenge.recovery'), ['code' => 'aaaaa-bbbbb'])->assertSessionHasErrors('code');
    }

    public function test_security_changes_of_an_account_are_logged(): void
    {
        $user = $this->userWithTotp(['password' => bcrypt(self::PASSWORD)]);
        $this->actingAs($user);

        $this->delete(route('mfa.totp.disable'), ['password' => self::PASSWORD]);
        $this->withSession($this->confirmed() + ['mfa_totp_setup_secret' => 'KRSXG5CTMVRXEZLU'])
            ->post(route('mfa.totp.enable'), ['code' => $this->totpCode('KRSXG5CTMVRXEZLU')]);
        $this->post(route('mfa.recovery-codes.regenerate'), ['password' => self::PASSWORD]);
        $this->put(route('password.update'), ['current_password' => self::PASSWORD, 'password' => 'Nouveau-Mot-2026', 'password_confirmation' => 'Nouveau-Mot-2026']);

        $this->assertSame(
            ['Double authentification désactivée', 'Double authentification activée', 'Codes de secours régénérés', 'Mot de passe modifié'],
            ActivityLog::orderBy('id')->pluck('action')->all(),
        );
        $this->assertSame("Application d'authentification", ActivityLog::where('action', 'Double authentification activée')->value('context'));
    }

    public function test_purging_the_activity_log_is_logged(): void
    {
        ActivityLog::create(['user_name' => 'Système', 'action' => 'Ancienne entrée', 'created_at' => now()->subDays(120)]);

        $this->actingAs($this->admin())->post(route('admin.logs.purge'));

        $this->assertSame(['Journal purgé'], ActivityLog::pluck('action')->all());
        $this->assertStringStartsWith('1 entrée', ActivityLog::value('context'));
    }
}
