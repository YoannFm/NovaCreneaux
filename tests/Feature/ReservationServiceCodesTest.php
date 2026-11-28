<?php

namespace Tests\Feature;

use App\Mail\ReservationCancelled;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Sanction;
use App\Models\Slot;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReservationServiceCodesTest extends TestCase
{
    use RefreshDatabase;

    private ReservationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReservationService::class);
        Mail::fake();
    }

    private function resource(array $attributes = []): Resource
    {
        return Resource::create($attributes + ['name' => 'CDI', 'open_time' => '00:00', 'close_time' => '23:59', 'closed_days' => []]);
    }

    private function slot(?Resource $resource = null, array $attributes = []): Slot
    {
        return Slot::create($attributes + [
            'resource_id' => ($resource ?? $this->resource())->id, 'name' => 'Creneau',
            'start_time' => '23:00', 'end_time' => '23:30', 'max_capacity' => 5,
        ]);
    }

    private function student(): User
    {
        return User::factory()->create(['role_id' => Role::defaultUser()->id]);
    }

    private function tomorrow(): string
    {
        return now()->addDay()->toDateString();
    }

    private function codeOf(User $user, Slot $slot, string $date): ?string
    {
        return $this->service->checkBookable($user, Slot::with('resource')->find($slot->id), $date)['code'] ?? null;
    }

    public function test_a_bookable_slot_passes_the_check_without_writing_anything(): void
    {
        $this->assertNull($this->codeOf($this->student(), $this->slot(), $this->tomorrow()));
        $this->assertSame(0, Reservation::count());
    }

    public function test_every_booking_failure_has_a_stable_code(): void
    {
        $user = $this->student();
        $slot = $this->slot();

        $this->assertSame('date_past', $this->codeOf($user, $slot, now()->subDay()->toDateString()));
        $this->assertSame('advance_limit', $this->codeOf($user, $slot, now()->addDays(31)->toDateString()));
        $this->assertSame('slot_unavailable', $this->codeOf($user, $this->slot(attributes: ['is_active' => false]), $this->tomorrow()));

        $closedDay = (int) now()->addDay()->format('N');
        $closed = $this->slot($this->resource(['name' => 'Ferme', 'closed_days' => [$closedDay]]));
        $this->assertSame('resource_closed_day', $this->codeOf($user, $closed, $this->tomorrow()));

        $started = $this->slot(attributes: ['start_time' => '00:00', 'end_time' => '00:01']);
        $this->assertSame('slot_started', $this->codeOf($user, $started, now()->toDateString()));

        $this->service->create($user, $slot->id, $this->tomorrow());
        $this->assertSame('already_booked', $this->codeOf($user, $slot, $this->tomorrow()));
    }

    public function test_capacity_and_limits_have_their_own_codes(): void
    {
        $full = $this->slot(attributes: ['max_capacity' => 1]);
        $this->service->create($this->student(), $full->id, $this->tomorrow());
        $this->assertSame('slot_full', $this->codeOf($this->student(), $full, $this->tomorrow()));

        Role::defaultUser()->update(['max_reservations_day' => 1, 'max_reservations_week' => 1]);
        $user = $this->student();
        $this->service->create($user, $this->slot()->id, $this->tomorrow());
        $this->assertSame('daily_limit', $this->codeOf($user, $this->slot(), $this->tomorrow()));

        Role::defaultUser()->update(['max_reservations_day' => -1]);
        $this->assertSame('weekly_limit', $this->codeOf($user->fresh(), $this->slot(), $this->tomorrow()));
    }

    public function test_a_banned_user_gets_the_banned_code(): void
    {
        $user = $this->student();
        Sanction::create(['user_id' => $user->id, 'admin_id' => $user->id, 'type' => 'ban', 'reason' => 'test', 'banned_until' => null]);

        $this->assertSame('banned', $this->codeOf($user, $this->slot(), $this->tomorrow()));
    }

    public function test_cancel_failures_have_codes(): void
    {
        $user = $this->student();
        $slot = $this->slot();
        $reservation = Reservation::create(['user_id' => $user->id, 'slot_id' => $slot->id, 'reservation_date' => now()->subDay()->toDateString()]);
        $this->assertSame('reservation_past', $this->service->cancel($user, $reservation->id)['code']);

        Role::defaultUser()->update(['cancel_deadline_minutes' => 60 * 24 * 7]);
        $soon = Reservation::create(['user_id' => $user->id, 'slot_id' => $slot->id, 'reservation_date' => $this->tomorrow()]);
        $this->assertSame('cancel_deadline', $this->service->cancel($user->fresh(), $soon->id)['code']);
    }

    public function test_an_admin_cancellation_removes_the_reservation_and_notifies_the_student(): void
    {
        $user = $this->student();
        $reservation = Reservation::create(['user_id' => $user->id, 'slot_id' => $this->slot()->id, 'reservation_date' => $this->tomorrow()]);

        $this->service->cancelByAdmin($reservation);

        $this->assertSame(0, Reservation::count());
        Mail::assertSent(ReservationCancelled::class, fn ($mail) => $mail->hasTo($user->email));
    }
}
