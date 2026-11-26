<?php

namespace Tests\Feature;

use App\Mail\EmailChanged;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Parametres > Authentification : l'administrateur choisit si chacun peut changer son nom, son adresse et sa classe. */
class ProfileFieldsSettingTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private const CHANGES = ['name' => 'Nouveau Nom', 'email' => 'nouvelle@exemple.fr', 'classe' => 'Tle B', 'current_password' => 'password'];

    public function test_the_three_options_are_on_by_default_and_saved_from_the_admin_page(): void
    {
        $this->actingAs($this->admin());
        $page = $this->get(route('admin.settings.auth'))->assertOk();
        foreach (['allow_name_change', 'allow_email_change', 'allow_classe_change'] as $key) {
            $this->assertMatchesRegularExpression('/id="' . $key . '" name="' . $key . '" value="1"[^>]*checked/', $page->getContent());
        }
        $page->assertSee('Permettre aux utilisateurs de changer leur classe');

        $this->post(route('admin.settings.auth.update'), ['allow_name_change' => '1', 'password_min_length' => 8])->assertSessionHasNoErrors();

        $this->assertSame(['1', '0', '0'], [setting('allow_name_change'), setting('allow_email_change'), setting('allow_classe_change')]);
    }

    public function test_everything_can_be_changed_when_allowed(): void
    {
        $user = $this->student(['name' => 'Léa', 'email' => 'lea@exemple.fr', 'classe' => '2nde A']);

        $this->actingAs($user)->patch(route('profile.update'), self::CHANGES)->assertSessionHasNoErrors();

        $this->assertSame(['Nouveau Nom', 'nouvelle@exemple.fr', 'Tle B'], array_values($user->fresh()->only('name', 'email', 'classe')));
    }

    /** Chaque champ verrouille garde sa valeur, meme envoyee a la main ; les autres changent. */
    public function test_a_locked_field_is_shown_but_never_changed(): void
    {
        Mail::fake();
        foreach (['name' => 'allow_name_change', 'email' => 'allow_email_change', 'classe' => 'allow_classe_change'] as $field => $key) {
            app(SettingService::class)->set($key, '0');
            $user = $this->student(['name' => 'Léa', 'email' => "lea.{$field}@exemple.fr", 'classe' => '2nde A']);
            $before = $user->only('name', 'email', 'classe');
            $this->actingAs($user);

            $this->get(route('profile.edit'))->assertOk()->assertSee("Modification désactivée par l'administrateur.");
            $this->patch(route('profile.update'), ['email' => "autre.{$field}@exemple.fr"] + self::CHANGES)->assertSessionHasNoErrors();

            $user->refresh();
            $this->assertSame($before[$field], $user->{$field}, "{$field} verrouille");
            foreach (array_diff(['name', 'email', 'classe'], [$field]) as $other) {
                $this->assertNotSame($before[$other], $user->{$other}, "{$other} reste modifiable");
            }
            app(SettingService::class)->set($key, '1');
        }
        Mail::assertSent(EmailChanged::class, 2);
    }

    public function test_a_locked_email_needs_no_password_and_warns_nobody(): void
    {
        Mail::fake();
        app(SettingService::class)->set('allow_email_change', '0');
        $user = $this->student(['email' => 'lea@exemple.fr']);

        $this->actingAs($user)->get(route('profile.edit'))->assertDontSee('name="current_password" autocomplete="current-password"', false);
        $this->patch(route('profile.update'), ['name' => 'Léa M', 'email' => 'pirate@exemple.fr'])->assertSessionHasNoErrors();

        $this->assertSame('lea@exemple.fr', $user->fresh()->email);
        $this->assertSame('Léa M', $user->fresh()->name);
        Mail::assertNothingSent();
    }

    public function test_with_every_field_locked_the_form_has_nothing_to_save(): void
    {
        foreach (['allow_name_change', 'allow_email_change', 'allow_classe_change'] as $key) {
            app(SettingService::class)->set($key, '0');
        }

        $html = $this->actingAs($this->student())->get(route('profile.edit'))->assertOk()->getContent();

        preg_match('#action="' . preg_quote(route('profile.update'), '#') . '".*?</form>#s', $html, $form);
        $this->assertStringNotContainsString('type="submit"', $form[0]);
    }
}
