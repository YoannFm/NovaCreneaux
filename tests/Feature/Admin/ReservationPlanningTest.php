<?php

namespace Tests\Feature\Admin;

use App\Mail\ReservationConfirmed;
use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Sanction;
use App\Models\Slot;
use App\Models\User;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Admin > Reservations : planning jour par jour, et reservation faite par le personnel pour un eleve. */
class ReservationPlanningTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private Resource $cdi;
    private Slot $m1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cdi = Resource::create(['name' => 'CDI', 'is_active' => true, 'open_time' => '12:00', 'close_time' => '23:59', 'closed_days' => []]);
        $this->m1 = Slot::create(['resource_id' => $this->cdi->id, 'name' => 'M1', 'start_time' => '23:00', 'end_time' => '23:30', 'max_capacity' => 2, 'is_active' => true]);
    }

    private function tomorrow(): string
    {
        return now()->addDay()->toDateString();
    }

    private function book(User $user, Slot $slot, ?string $date = null): Reservation
    {
        return Reservation::create(['user_id' => $user->id, 'slot_id' => $slot->id, 'reservation_date' => $date ?? $this->tomorrow()]);
    }

    private function add(array $fields)
    {
        return $this->from(route('admin.reservations.index', ['date' => $this->tomorrow()]))
            ->post(route('admin.reservations.store'), $fields + ['slot_id' => $this->m1->id, 'date' => $this->tomorrow()]);
    }

    // ---- planning ---------------------------------------------------------------------------------

    public function test_the_day_is_shown_by_resource_and_slot_with_links_to_the_previous_and_next_day(): void
    {
        $this->book($this->student(['name' => 'Zoé Moreau']), $this->m1);
        $this->book($this->student(['name' => 'Élodie Durand']), $this->m1);
        $other = Resource::create(['name' => 'Postes', 'is_active' => true, 'open_time' => '12:00', 'close_time' => '23:59', 'closed_days' => []]);
        Slot::create(['resource_id' => $other->id, 'name' => 'Récré', 'start_time' => '10:00', 'end_time' => '10:15', 'max_capacity' => 5, 'is_active' => true]);
        $date = now()->addDay();

        $page = $this->actingAs($this->admin())->get(route('admin.reservations.index', ['date' => $date->toDateString(), 'resource_id' => $this->cdi->id]))->assertOk();

        $page->assertSee(ucfirst($date->locale('fr')->isoFormat('dddd D MMMM YYYY')))
            ->assertSee('href="' . e(route('admin.reservations.index', ['date' => $date->copy()->subDay()->toDateString(), 'resource_id' => $this->cdi->id])) . '"', false)
            ->assertSee('href="' . e(route('admin.reservations.index', ['date' => $date->copy()->addDay()->toDateString(), 'resource_id' => $this->cdi->id])) . '"', false)
            ->assertSee('2 / 2')->assertDontSee('Récré');
        // Tri sans tenir compte des accents
        $this->assertMatchesRegularExpression('/Élodie Durand.*Zoé Moreau/s', $page->getContent());
    }

    public function test_an_inactive_slot_still_shows_its_reservations_but_takes_no_new_ones(): void
    {
        $this->book($this->student(['name' => 'Inscrit Avant']), $this->m1);
        $this->m1->update(['is_active' => false]);

        $this->actingAs($this->admin())->get(route('admin.reservations.index', ['date' => $this->tomorrow()]))->assertOk()
            ->assertSee('Inscrit Avant')->assertSee('Inactif');
        $this->add(['user_id' => $this->student()->id])->assertSessionHasErrorsIn('add', 'slot_id');
    }

    // ---- ajout par le personnel -------------------------------------------------------------------

    public function test_staff_can_book_for_a_student_beyond_the_rules_of_their_role(): void
    {
        Mail::fake();
        Role::defaultUser()->update(['max_reservations_day' => 0, 'advance_days' => 1]);
        $student = $this->student(['name' => 'Sans Accès']);
        Sanction::create(['user_id' => $student->id, 'admin_id' => $this->admin()->id, 'type' => 'ban', 'reason' => 'x']);
        $this->actingAs($this->admin());

        $this->add(['user_id' => $student->id, 'date' => now()->addDays(10)->toDateString()])
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'Sans Accès est inscrit(e) : CDI, M1.');

        $this->assertTrue(Reservation::where('user_id', $student->id)->exists());
        Mail::assertSent(ReservationConfirmed::class, fn ($mail) => $mail->hasTo($student->email));
        $this->assertTrue(ActivityLog::where('action', 'Réservation ajoutée (admin)')->exists());
    }

    public function test_a_full_slot_a_duplicate_or_a_past_day_is_refused_and_the_window_reopens(): void
    {
        $this->actingAs($this->admin());
        $student = $this->student();
        $this->book($student, $this->m1);

        $this->add(['user_id' => $student->id])->assertSessionHasErrorsIn('add', ['slot_id' => "{$student->name} a déjà réservé ce créneau."]);
        $this->book($this->student(), $this->m1);
        $this->add(['user_id' => $this->student()->id])->assertSessionHasErrorsIn('add', ['slot_id' => 'Ce créneau est complet.']);
        $this->add(['user_id' => $this->student()->id, 'date' => now()->subDay()->toDateString()])->assertSessionHasErrorsIn('add', ['slot_id' => 'Cette date est passée.']);
        // Sans personne choisie : la fenetre se rouvre avec le message (verifie sur la page, l'assertion de session consommerait les erreurs)
        $this->add([]);
        $this->get(route('admin.reservations.index', ['date' => $this->tomorrow()]))
            ->assertSee('data-reopen="1"', false)->assertSee('Le champ personne est obligatoire.');
        $this->assertSame(2, Reservation::count());
    }

    public function test_delegated_staff_cannot_book_for_an_administrator(): void
    {
        $this->actingAs($this->staff([Permission::RESERVATIONS]));

        $this->add(['user_id' => $this->admin()->id])->assertForbidden();
        $this->add(['user_id' => $this->student()->id])->assertSessionHasNoErrors();
    }

    // ---- recherche --------------------------------------------------------------------------------

    public function test_the_search_finds_active_accounts_the_staff_member_manages(): void
    {
        $this->student(['name' => 'Léa Martin', 'classe' => '2nde A']);
        $this->student(['name' => 'Léa Inactive', 'is_active' => false]);
        $suspended = $this->student(['name' => 'Léa Suspendue']);
        Sanction::create(['user_id' => $suspended->id, 'admin_id' => $this->admin()->id, 'type' => 'ban', 'reason' => 'x']);
        $this->admin()->update(['name' => 'Léa Admin']);

        $results = $this->actingAs($this->staff([Permission::RESERVATIONS]))
            ->getJson(route('admin.reservations.users', ['q' => 'Léa']))->assertOk()->json();

        $this->assertSame(['Léa Martin', 'Léa Suspendue'], array_column($results, 'name'));
        $this->assertSame('2nde A', $results[0]['classe']);
        $this->assertTrue($results[1]['suspended']);
        $this->getJson(route('admin.reservations.users', ['q' => 'L']))->assertOk()->assertExactJson([]);
    }

    public function test_students_cannot_search_or_add(): void
    {
        $this->actingAs($this->student());

        $this->get(route('admin.reservations.users', ['q' => 'Léa']))->assertRedirect('/');
        $this->add(['user_id' => $this->student()->id])->assertRedirect('/');
        $this->assertSame(0, Reservation::count());
    }
}
