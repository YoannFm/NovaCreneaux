<?php

namespace Tests\Feature;

use App\Mail\EmailVerificationLink;
use App\Models\Role;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Toutes les pages d'authentification partagent layouts/auth et sont en francais. */
class AuthPagesTest extends TestCase
{
    use RefreshDatabase;

    /** Marque du modele commun (carte de connexion et bouton du theme). */
    private const LAYOUT = 'class="auth-card"';

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role_id' => Role::defaultUser()->id]);
    }

    public function test_the_email_verification_page_is_in_french_and_sends_a_french_link_that_works(): void
    {
        Mail::fake();
        $user = $this->user(['email_verified_at' => null, 'email' => 'lea@exemple.fr']);
        $this->actingAs($user);

        $this->get(route('verification.notice'))->assertOk()->assertSee(self::LAYOUT, false)
            ->assertSee('Confirmer votre adresse e-mail')->assertSee('lea@exemple.fr')->assertDontSee('Thanks for signing up');

        $this->from(route('verification.notice'))->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'))->assertSessionHas('status', 'Lien envoyé : regardez votre boîte de réception.');

        $url = null;
        Mail::assertSent(EmailVerificationLink::class, function (EmailVerificationLink $mail) use (&$url) {
            $url = $mail->url;

            return $mail->hasTo('lea@exemple.fr') && str_contains($mail->render(), 'Confirmer mon adresse');
        });
        $this->get($url)->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_failed_verification_email_shows_a_clear_error(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('serveur SMTP injoignable'));
        $this->actingAs($this->user(['email_verified_at' => null]));

        $this->from(route('verification.notice'))->post(route('verification.send'))->assertSessionHas('error');
        $this->get(route('verification.notice'))->assertSee("L'e-mail n'a pas pu être envoyé");
    }

    public function test_the_password_confirmation_page_is_in_french(): void
    {
        $this->actingAs($this->user())->get(route('password.confirm'))->assertOk()->assertSee(self::LAYOUT, false)
            ->assertSee('Confirmer votre mot de passe')->assertDontSee('secure area');
    }

    public function test_the_registration_page_uses_the_auth_layout(): void
    {
        app(SettingService::class)->set('registration_open', '1');

        $this->get(route('register'))->assertOk()->assertSee(self::LAYOUT, false)
            ->assertSee('Créer un compte')->assertSee("S'inscrire", false);
    }

    public function test_the_two_factor_pages_use_the_auth_layout(): void
    {
        $user = $this->user(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now(), 'mfa_email_enabled' => true]);
        $session = ['mfa_user_id' => $user->id];

        $this->withSession($session)->get(route('mfa.challenge.form'))->assertOk()->assertSee(self::LAYOUT, false)
            ->assertSee('Double authentification')->assertSee('Envoyer le code par e-mail')->assertSee('function switchMethod', false);
        $this->withSession($session)->get(route('mfa.challenge.recovery.form'))->assertOk()->assertSee(self::LAYOUT, false)
            ->assertSee('Code de récupération');
    }
}
