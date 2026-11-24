<?php

namespace Tests\Feature\Auth;

use App\Mail\PasswordResetLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    /** Demande un lien et renvoie l'e-mail envoye au compte. */
    private function requestLink(User $user): PasswordResetLink
    {
        Mail::fake();
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();

        $sent = null;
        Mail::assertSent(PasswordResetLink::class, function (PasswordResetLink $mail) use ($user, &$sent) {
            $sent = $mail;
            return $mail->hasTo($user->email);
        });

        return $sent;
    }

    private function tokenFrom(PasswordResetLink $mail): string
    {
        return basename(parse_url($mail->url, PHP_URL_PATH));
    }

    public function test_the_forgot_password_page_is_in_french_with_a_way_back_to_login(): void
    {
        $this->get(route('password.request'))->assertOk()
            ->assertSee('Mot de passe oublié')->assertSee('Recevoir le lien')
            ->assertSee('60 minutes')->assertSee('href="' . route('login') . '"', false);
    }

    public function test_the_login_page_still_works_and_links_to_the_forgot_password_page(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('Se connecter')->assertSee('Mot de passe oublié ?')
            ->assertSee('href="' . route('password.request') . '"', false);
    }

    public function test_a_french_email_with_a_working_link_is_sent_to_the_account(): void
    {
        $user = User::factory()->create(['name' => 'Léa Martin']);

        $mail = $this->requestLink($user);

        $this->assertStringStartsWith(route('password.reset', ['token' => $this->tokenFrom($mail)]), $mail->url);
        $this->assertStringContainsString('email=' . urlencode($user->email), $mail->url);
        $mail->assertSeeInHtml('Léa Martin')->assertSeeInHtml('Choisir un nouveau mot de passe')->assertSeeInHtml('60 minutes');
        $this->assertStringStartsWith('Réinitialiser votre mot de passe', $mail->envelope()->subject);
    }

    public function test_an_unknown_address_gets_the_same_answer_and_no_email(): void
    {
        Mail::fake();
        $known = User::factory()->create();

        $a = $this->post(route('password.email'), ['email' => $known->email]);
        $b = $this->post(route('password.email'), ['email' => 'inconnu@example.com']);

        $this->assertSame($a->getSession()->get('status'), $b->getSession()->get('status'));
        Mail::assertSentCount(1);
    }

    public function test_a_failing_mail_server_does_not_break_the_page_nor_reveal_the_account(): void
    {
        $user = User::factory()->create();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP en panne'));

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()->assertSessionHas('status');
    }

    public function test_the_reset_page_prefills_the_address(): void
    {
        $user = User::factory()->create();
        $mail = $this->requestLink($user);

        $this->get($mail->url)->assertOk()
            ->assertSee('Nouveau mot de passe')->assertSee('value="' . $user->email . '"', false);
    }

    public function test_the_password_is_replaced_and_the_change_date_recorded(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFrom($this->requestLink($user));

        $this->post(route('password.store'), [
            'token' => $token, 'email' => $user->email,
            'password' => 'Nouveau-Mot-2026', 'password_confirmation' => 'Nouveau-Mot-2026',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Votre mot de passe a été réinitialisé.');

        $user->refresh();
        $this->assertTrue(Hash::check('Nouveau-Mot-2026', $user->password));
        $this->assertNotNull($user->password_changed_at);
    }

    public function test_a_wrong_token_is_refused_in_french(): void
    {
        $user = User::factory()->create();

        $this->from(route('password.reset', ['token' => 'faux']))->post(route('password.store'), [
            'token' => 'faux', 'email' => $user->email,
            'password' => 'Nouveau-Mot-2026', 'password_confirmation' => 'Nouveau-Mot-2026',
        ])->assertSessionHasErrors(['email' => "Ce jeton de réinitialisation du mot de passe n'est pas valide."]);
    }

    public function test_requests_are_limited_per_ip(): void
    {
        Mail::fake();

        foreach (range(1, 6) as $i) {
            $this->post(route('password.email'), ['email' => "x{$i}@example.com"])->assertRedirect();
        }
        $this->post(route('password.email'), ['email' => 'x7@example.com'])->assertStatus(429);
    }
}
