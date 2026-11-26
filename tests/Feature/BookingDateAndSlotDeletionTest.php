<?php

namespace Tests\Feature;

use App\Mail\ReservationCancelled;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Slot;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/**
 * Une date ecrite autrement ("2026-11-25 00:00:00") ne contourne plus les regles du jour meme, et supprimer un
 * creneau ne previent personne d'une seance deja terminee et garde l'historique des reservations passees.
 */
class BookingDateAndSlotDeletionTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private Slot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-25 18:00:00');
        $cdi = Resource::create(['name' => 'CDI', 'is_active' => true, 'open_time' => '17:00', 'close_time' => '11:30', 'closed_days' => []]);
        $this->slot = $cdi->slots()->create(['name' => 'M1', 'start_time' => '08:00', 'end_time' => '09:00', 'max_capacity' => 24, 'is_active' => true]);
    }

    public function test_the_site_form_only_accepts_a_plain_day(): void
    {
        $this->actingAs($this->student());

        $this->post(route('reservations.store'), ['slot_id' => $this->slot->id, 'reservation_date' => '2026-11-25 00:00:00'])
            ->assertSessionHasErrors('reservation_date');

        $this->assertSame(0, Reservation::count());
    }

    public function test_the_rules_see_the_same_day_whatever_its_spelling(): void
    {
        $student = $this->student();
        $service = app(ReservationService::class);

        foreach (['2026-11-25', '2026-11-25 00:00:00', '2026-11-25T00:00:00'] as $spelling) {
            $this->assertSame('resource_closed_today', $service->create($student, $this->slot->id, $spelling)['code'] ?? null, $spelling);
            $this->assertSame(['allowed' => false, 'code' => 'resource_closed_today', 'reason' => "Réservations fermées pour aujourd'hui."],
                $this->slot->resource->canReserveFor($spelling, 1), $spelling);
        }

        // Le personnel non plus ne peut pas ajouter une seance terminee
        $this->assertSame('slot_started', $service->createByStaff($student, $this->slot->id, '2026-11-25 00:00:00')['code'] ?? null);
        $this->assertSame(0, Reservation::count());
    }

    public function test_deleting_a_slot_only_cancels_and_warns_for_sessions_still_to_come(): void
    {
        Mail::fake();
        $this->actingAs($this->admin());
        $done = Reservation::create(['user_id' => $this->student(['name' => 'Déjà venue'])->id, 'slot_id' => $this->slot->id, 'reservation_date' => '2026-11-25']);
        $coming = Reservation::create(['user_id' => $this->student(['name' => 'Demain'])->id, 'slot_id' => $this->slot->id, 'reservation_date' => '2026-11-26']);

        $this->delete(route('admin.slots.destroy', [$this->slot->resource, $this->slot]))
            ->assertSessionHas('success', 'Créneau supprimé. 1 réservation(s) à venir annulée(s), les personnes concernées ont été prévenues.');

        Mail::assertSent(ReservationCancelled::class, 1);
        Mail::assertSent(ReservationCancelled::class, fn ($mail) => $mail->hasTo($coming->user->email));
        $this->assertFalse(UserNotification::where('user_id', $done->user_id)->exists(), "pas de message pour une seance deja passee");
        $this->assertTrue(UserNotification::where('user_id', $coming->user_id)->exists());
    }

    public function test_a_deleted_slot_keeps_its_past_reservations_in_the_history_and_can_no_longer_be_booked(): void
    {
        Mail::fake();
        $student = $this->student(['name' => 'Déjà venue']);
        $done = Reservation::create(['user_id' => $student->id, 'slot_id' => $this->slot->id, 'reservation_date' => '2026-11-24']);
        $coming = Reservation::create(['user_id' => $this->student()->id, 'slot_id' => $this->slot->id, 'reservation_date' => '2026-11-26']);

        $this->actingAs($this->admin())->delete(route('admin.slots.destroy', [$this->slot->resource, $this->slot]))->assertSessionHasNoErrors();

        $this->assertTrue($done->fresh()->exists(), "la reservation passee reste");
        $this->assertNull($coming->fresh(), "celle de demain est annulee");
        $this->get(route('admin.reservations.index', ['date' => '2026-11-24']))->assertOk()
            ->assertSee('Déjà venue')->assertSee('M1')->assertSee('Supprimé');
        $this->get(route('admin.reservations.index'))->assertOk()->assertDontSee('M1');
        $this->actingAs($student)->get(route('reservations.my'))->assertOk()->assertSee('CDI')->assertSee('M1');

        $this->post(route('reservations.store'), ['slot_id' => $this->slot->id, 'reservation_date' => '2026-11-26'])->assertSessionHasErrors('slot_id');
        $this->assertSame(1, Reservation::count());
    }

    public function test_a_deleted_resource_keeps_its_past_reservations_and_its_slots_disappear(): void
    {
        $done = Reservation::create(['user_id' => $this->student(['name' => 'Déjà venue'])->id, 'slot_id' => $this->slot->id, 'reservation_date' => '2026-11-24']);
        $resource = $this->slot->resource;

        $this->actingAs($this->admin())->delete(route('admin.resources.destroy', $resource))->assertSessionHasNoErrors();

        $this->assertTrue($done->fresh()->exists());
        $this->assertSame(0, Slot::count(), 'ses creneaux sont supprimes avec elle');
        $this->assertSame('CDI', $done->fresh()->slot->resource->name);
        $this->get(route('admin.reservations.index', ['date' => '2026-11-24']))->assertOk()->assertSee('Déjà venue')->assertSee('CDI');
        $this->get(route('admin.resources.index'))->assertOk()->assertDontSee('CDI');
    }
}
