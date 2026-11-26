<?php

namespace Tests\Feature\Admin;

use App\Mail\ReservationCancelled;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Slot;
use App\Models\User;
use App\Services\UserCsvImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Meme longueur de classe partout, et un compte desactive libere ses places a venir. */
class UserClasseAndDeactivationTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    public function test_the_same_classe_length_is_accepted_everywhere(): void
    {
        $classe = Str::repeat('a', User::CLASSE_MAX);
        $student = $this->student(['classe' => $classe]);

        // Fiche de l'eleve par l'administrateur : seul le nom change
        $this->actingAs($this->admin())->put(route('admin.users.update', $student), [
            'name' => 'Nouveau Nom', 'email' => $student->email, 'classe' => $classe, 'role_id' => $student->role_id,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Nouveau Nom', $student->fresh()->name);

        // Profil de l'eleve
        $this->actingAs($student->fresh())->patch(route('profile.update'), ['name' => 'Nom', 'email' => $student->email, 'classe' => $classe])->assertSessionHasNoErrors();
        $this->patch(route('profile.update'), ['name' => 'Nom', 'email' => $student->email, 'classe' => $classe . 'a'])->assertSessionHasErrors('classe');
    }

    public function test_registration_accepts_the_same_length(): void
    {
        app(\App\Services\SettingService::class)->set('registration_open', '1');

        $this->post(route('register'), [
            'name' => 'Léa', 'email' => 'lea@exemple.fr', 'classe' => Str::repeat('b', User::CLASSE_MAX),
            'password' => 'MotDePasse-2026', 'password_confirmation' => 'MotDePasse-2026',
        ])->assertSessionDoesntHaveErrors('classe');

        $this->assertSame(User::CLASSE_MAX, mb_strlen((string) User::where('email', 'lea@exemple.fr')->value('classe')));
    }

    public function test_the_csv_import_shortens_a_longer_classe(): void
    {
        $this->actingAs($this->admin());
        $file = UploadedFile::fake()->createWithContent('eleves.csv', "Martin;Léa;" . Str::repeat('c', 150) . ";lea@exemple.fr\n");

        app(UserCsvImport::class)->import($file->getRealPath());

        $this->assertSame(User::CLASSE_MAX, mb_strlen((string) User::where('email', 'lea@exemple.fr')->value('classe')));
    }

    public function test_the_migration_shortens_existing_classes(): void
    {
        $student = $this->student();
        DB::table('users')->where('id', $student->id)->update(['classe' => Str::repeat('d', 200)]);

        (require database_path('migrations/2026_11_27_000002_limit_user_classe_length.php'))->up();

        $this->assertSame(100, mb_strlen($student->fresh()->classe));
    }

    private function bookedSlot(User $student): array
    {
        $this->travelTo('2026-11-25 10:00:00');
        $slot = Resource::create(['name' => 'CDI', 'is_active' => true, 'open_time' => '00:00', 'close_time' => '23:59', 'closed_days' => []])
            ->slots()->create(['name' => 'M1', 'start_time' => '08:00', 'end_time' => '09:00', 'max_capacity' => 1, 'is_active' => true]);
        $past = Reservation::create(['user_id' => $student->id, 'slot_id' => $slot->id, 'reservation_date' => '2026-11-24']);
        $coming = Reservation::create(['user_id' => $student->id, 'slot_id' => $slot->id, 'reservation_date' => '2026-11-26']);

        return [$slot, $past, $coming];
    }

    public function test_deactivating_an_account_frees_its_upcoming_places_and_keeps_its_history(): void
    {
        Mail::fake();
        $student = $this->student();
        [$slot, $past, $coming] = $this->bookedSlot($student);

        $this->actingAs($this->admin())->patch(route('admin.users.toggle', $student))
            ->assertSessionHas('success', 'Statut mis à jour. 1 réservation(s) à venir annulée(s), les personnes concernées ont été prévenues.');

        $this->assertNull($coming->fresh());
        $this->assertNotNull($past->fresh(), "l'historique reste");
        $this->assertSame(1, $slot->availableCountFor('2026-11-26'));
        Mail::assertSent(ReservationCancelled::class, fn (ReservationCancelled $mail) => $mail->hasTo($student->email));

        // Reactiver ne fait rien revenir ni n'annule quoi que ce soit
        $this->patch(route('admin.users.toggle', $student))->assertSessionHas('success', 'Statut mis à jour.');
        $this->assertTrue($student->fresh()->is_active);
    }

    public function test_bulk_deactivation_frees_the_places_too(): void
    {
        Mail::fake();
        $student = $this->student();
        [, $past, $coming] = $this->bookedSlot($student);

        $this->actingAs($this->admin())->post(route('admin.users.bulk'), ['action' => 'deactivate', 'ids' => [$student->id]])
            ->assertSessionHas('success', '1 utilisateur(s) désactivé(s). 1 réservation(s) à venir annulée(s), les personnes concernées ont été prévenues.');

        $this->assertNull($coming->fresh());
        $this->assertNotNull($past->fresh());
    }
}
