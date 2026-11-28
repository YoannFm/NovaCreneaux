<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Une adresse e-mail est toujours enregistree en minuscules, et "Lea@..." et "lea@..." sont le meme compte. */
class EmailLowercaseTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    public function test_any_new_account_gets_its_address_in_lowercase(): void
    {
        $user = User::factory()->create(['email' => '  Lea.Martin@Exemple.FR ']);

        $this->assertSame('lea.martin@exemple.fr', $user->fresh()->email);
    }

    public function test_signing_up_with_capitals_works_and_the_same_address_cannot_sign_up_twice(): void
    {
        app(SettingService::class)->set('registration_open', '1');
        $form = ['name' => 'Léa Martin', 'password' => 'Mot-De-Passe-2026', 'password_confirmation' => 'Mot-De-Passe-2026'];

        // Avant : la regle "lowercase" refusait l'inscription au lieu d'accepter l'adresse
        $this->post(route('register'), $form + ['email' => 'Lea.Martin@Exemple.fr'])->assertSessionHasNoErrors();
        $this->assertTrue(User::where('email', 'lea.martin@exemple.fr')->exists());

        auth()->logout();
        $this->post(route('register'), $form + ['email' => 'lea.martin@EXEMPLE.fr'])->assertSessionHasErrors('email');
        $this->assertSame(1, User::where('email', 'lea.martin@exemple.fr')->count());
    }

    public function test_logging_in_with_capitals_finds_the_account(): void
    {
        $user = User::factory()->create(['email' => 'lea@exemple.fr', 'password' => bcrypt('Mot-De-Passe-2026'), 'role_id' => Role::defaultUser()->id]);

        $this->post(route('login'), ['email' => 'LEA@Exemple.fr', 'password' => 'Mot-De-Passe-2026']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_csv_import_lowercases_and_skips_an_address_already_known_with_other_capitals(): void
    {
        $this->student(['email' => 'zoe.moreau@exemple.fr']);
        $csv = "Nom,Prenom,Classe,Email\nMartin,Léa,2nde A,Lea.Martin@Exemple.FR\nMoreau,Zoé,1ère G,Zoe.Moreau@Exemple.fr\n";
        $file = UploadedFile::fake()->createWithContent('eleves.csv', $csv);

        $this->actingAs($this->admin())->post(route('admin.users.import.store'), ['csv' => $file])
            ->assertSessionHas('success', '1 utilisateur(s) importé(s), 1 ignoré(s).');

        $this->assertTrue(User::where('email', 'lea.martin@exemple.fr')->exists());
        $this->assertFalse(User::where('email', 'Lea.Martin@Exemple.FR')->exists());
    }

    public function test_the_admin_forms_and_the_profile_save_lowercase_and_refuse_a_case_variant(): void
    {
        $this->actingAs($this->admin());
        $this->student(['email' => 'pris@exemple.fr']);

        $this->post(route('admin.users.store'), ['name' => 'Hugo', 'email' => 'Hugo@Exemple.fr', 'role_id' => Role::defaultUser()->id])->assertSessionHasNoErrors();
        $this->assertTrue(User::where('email', 'hugo@exemple.fr')->exists());
        $this->post(route('admin.users.store'), ['name' => 'Copie', 'email' => 'PRIS@exemple.fr', 'role_id' => Role::defaultUser()->id])->assertSessionHasErrors('email');

        $student = $this->student();
        $this->actingAs($student)->patch('/profile', ['name' => $student->name, 'email' => 'Nouvelle.Adresse@Exemple.fr'])->assertSessionHasNoErrors();
        $this->assertSame('nouvelle.adresse@exemple.fr', $student->fresh()->email);
    }

    public function test_the_migration_lowercases_old_addresses_but_leaves_case_duplicates_alone(): void
    {
        // Ecriture directe : le modele mettrait deja en minuscules
        $single = User::factory()->create()->id;
        [$twinA, $twinB] = [User::factory()->create()->id, User::factory()->create()->id];
        DB::table('users')->where('id', $single)->update(['email' => 'Ancien@Exemple.fr']);
        DB::table('users')->where('id', $twinA)->update(['email' => 'Double@Exemple.fr']);
        DB::table('users')->where('id', $twinB)->update(['email' => 'double@exemple.fr']);

        (require database_path('migrations/2026_11_25_000002_lowercase_user_emails.php'))->up();

        $this->assertSame('ancien@exemple.fr', DB::table('users')->where('id', $single)->value('email'));
        $this->assertSame('Double@Exemple.fr', DB::table('users')->where('id', $twinA)->value('email'));
    }
}
