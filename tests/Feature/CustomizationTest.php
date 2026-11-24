<?php

namespace Tests\Feature;

use App\Mail\AccountDeleted;
use App\Mail\MfaOtpMail;
use App\Mail\PasswordResetLink;
use App\Mail\ReservationConfirmed;
use App\Mail\TestMail;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Slot;
use App\Models\User;
use App\Services\ReservationService;
use App\Services\SettingService;
use App\Services\Webhook\EventMessage;
use App\Support\Customization;
use App\Support\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class CustomizationTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private function set(array $values): void
    {
        foreach ($values as $key => $value) {
            app(SettingService::class)->set($key, $value);
        }
    }

    public function test_nothing_specific_to_one_school_is_shown_by_default(): void
    {
        $login = $this->get(route('login'))->assertOk();

        $login->assertSee('placeholder="prenom.nom@exemple.fr"', false)->assertSee('Réservation de créneaux');
        foreach ([$login->getContent(), $this->get(route('password.request'))->getContent()] as $html) {
            $this->assertDoesNotMatchRegularExpression('/rascol|\bCDI\b/i', $html);
        }
    }

    public function test_the_general_page_saves_the_customization_and_the_site_uses_it(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.settings.general.update'), [
            'app_name' => 'Médiathèque', 'app_tagline' => 'Salles de travail', 'email_placeholder' => 'nom@ville.fr',
            'contact_label' => "l'accueil", 'user_term' => 'adhérent', 'user_term_plural' => 'adhérents', 'user_term_feminine' => '0',
            'group_label' => 'Section', 'group_placeholder' => 'ex. Jeunesse', 'default_theme' => 'light',
        ])->assertSessionHasNoErrors();

        $this->get(route('admin.users.index'))->assertSee('<th scope="col">Section</th>', false);
        auth()->logout();
        $this->get(route('login'))->assertSee('placeholder="nom@ville.fr"', false)->assertSee('Salles de travail')
            ->assertSee("|| 'light'", false);
        $this->assertSame("l'adhérent", Customization::theUser());
    }

    public function test_an_empty_field_falls_back_to_the_default(): void
    {
        $this->set(['user_term' => '', 'email_placeholder' => '']);

        $this->assertSame('élève', Customization::userTerm());
        $this->assertSame('prenom.nom@exemple.fr', Customization::get('email_placeholder'));
    }

    public function test_articles_follow_the_gender_and_the_first_letter(): void
    {
        $this->assertSame(['un élève', "l'élève"], [Customization::aUser(), Customization::theUser()]);

        $this->set(['user_term' => 'stagiaire', 'user_term_feminine' => '1']);
        $this->assertSame(['une stagiaire', 'la stagiaire'], [Customization::aUser(), Customization::theUser()]);

        $this->set(['user_term' => 'membre', 'user_term_feminine' => '0']);
        $this->assertSame(['un membre', 'le membre'], [Customization::aUser(), Customization::theUser()]);
    }

    public function test_webhook_messages_use_the_chosen_word_when_names_are_hidden(): void
    {
        $this->set(['user_term' => 'adhérente', 'user_term_feminine' => '1']);
        $payload = ['id' => 'x', 'event' => WebhookEvent::RESERVATION_CANCELLED, 'created_at' => now()->toIso8601String(), 'data' => [
            'user' => ['name' => 'Léa', 'classe' => null], 'cancelled_by' => 'user',
            'reservation' => ['date' => '2026-12-01', 'resource' => ['name' => 'Salle'], 'slot' => ['name' => 'M1', 'start_time' => '08:00', 'end_time' => '09:00']],
        ]];

        $hidden = EventMessage::variables($payload, false, 'Site', 'd1');
        $shown = EventMessage::variables($payload, true, 'Site', 'd1');

        $this->assertSame('une adhérente', $hidden['who']);
        $this->assertStringContainsString("Annulée par l'adhérente : une adhérente", $hidden['text']);
        $this->assertSame('Léa', $shown['who']);
    }

    public function test_the_suspension_message_and_the_deletion_email_name_the_contact(): void
    {
        $this->set(['contact_label' => 'le CDI']);
        $student = $this->student();
        $student->sanctions()->create(['admin_id' => $this->admin()->id, 'type' => 'ban', 'reason' => 'Bruit']);
        $resource = Resource::create(['name' => 'Salle', 'open_time' => '00:00', 'close_time' => '23:59', 'closed_days' => []]);
        $slot = Slot::create(['resource_id' => $resource->id, 'name' => 'M1', 'start_time' => '08:00', 'end_time' => '09:00', 'max_capacity' => 5]);

        $failure = app(ReservationService::class)->checkBookable($student, $slot, now()->addDay()->toDateString());

        $this->assertSame('Votre accès aux réservations est suspendu définitivement. Motif : Bruit Pour toute question, contactez le CDI.', $failure['error']);
        (new AccountDeleted('Léa', true))->assertSeeInHtml('contactez le CDI');
    }

    public function test_every_email_uses_the_shared_template_with_color_and_signature(): void
    {
        $this->set(['mail_color' => '#123456', 'mail_signature' => "L'équipe\n<b>Accueil</b>"]);
        $resource = Resource::create(['name' => 'Salle', 'open_time' => '00:00', 'close_time' => '23:59', 'closed_days' => []]);
        $slot = Slot::create(['resource_id' => $resource->id, 'name' => 'M1', 'start_time' => '08:00', 'end_time' => '09:00', 'max_capacity' => 5]);
        $reservation = Reservation::create(['user_id' => $this->student()->id, 'slot_id' => $slot->id, 'reservation_date' => now()->addDay()->toDateString()]);

        foreach ([new ReservationConfirmed($reservation), new AccountDeleted('Léa', false), new PasswordResetLink('Léa', 'https://x.test/r', 60), new MfaOtpMail('123456', 'email'), new TestMail()] as $mail) {
            $mail->assertSeeInHtml('background:#123456', false)
                ->assertSeeInHtml("L&#039;équipe<br />\n&lt;b&gt;Accueil&lt;/b&gt;", false)
                ->assertSeeInHtml('merci de ne pas y répondre');
        }
        // Bouton : texte blanc sur fond sombre
        (new PasswordResetLink('Léa', 'https://x.test/r', 60))->assertSeeInHtml('background:#123456;color:#ffffff', false);
    }

    public function test_the_mail_page_switches_between_the_site_color_and_a_custom_one(): void
    {
        $this->actingAs($this->admin());
        $base = ['mail_driver' => 'disabled'];

        $this->post(route('admin.settings.mail.update'), $base + ['mail_use_site_color' => '0', 'mail_color' => '#00aa00', 'mail_signature' => 'Signé'])->assertSessionHasNoErrors();
        $this->assertSame('#00aa00', Customization::mailColor());

        $this->post(route('admin.settings.mail.update'), $base + ['mail_use_site_color' => '0', 'mail_color' => 'rouge'])->assertSessionHasErrors('mail_color');
        $this->post(route('admin.settings.mail.update'), $base + ['mail_use_site_color' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('#efb703', Customization::mailColor());
    }

    public function test_the_minimum_password_length_is_configurable_and_applied(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.settings.auth.update'), ['password_min_length' => 4])->assertSessionHasErrors('password_min_length');
        $this->post(route('admin.settings.auth.update'), ['password_min_length' => 12])->assertSessionHasNoErrors();
        $this->assertSame(12, Customization::passwordMinLength());

        $this->post(route('admin.users.store'), ['name' => 'Court', 'email' => 'court@example.com', 'password' => 'abcdefghij', 'role_id' => Role::defaultUser()->id])
            ->assertSessionHasErrors('password');
        $this->post(route('admin.users.store'), ['name' => 'Long', 'email' => 'long@example.com', 'password' => 'abcdefghijkl', 'role_id' => Role::defaultUser()->id])
            ->assertSessionHasNoErrors();
        auth()->logout();
        $this->get(route('password.reset', ['token' => 'x']))->assertSee('minlength="12"', false)->assertSee('12 caractères minimum');
    }

    public function test_the_authenticator_app_shows_the_configured_site_name(): void
    {
        $this->set(['app_name' => 'Ma Médiathèque']);

        $this->actingAs($this->student())->get(route('mfa.totp.enable.form'))->assertOk()
            ->assertSee(urlencode(rawurlencode('Ma Médiathèque')), false);
    }

    public function test_the_footer_shows_the_years_since_2025(): void
    {
        $this->get(route('login'));
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Copyright 2025 - ' . now()->year);
    }

    public function test_the_logo_uploaded_in_the_settings_is_shown_on_the_site(): void
    {
        $this->set(['site_logo' => 'uploads/logo/logo_test.png']);

        $this->actingAs($this->student())->get(route('reservations.my'))->assertOk()->assertSee('uploads/logo/logo_test.png');
    }
}
