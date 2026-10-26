<?php

namespace Tests\Feature\Api;

use App\Mail\ReservationCancelled;
use App\Mail\ReservationConfirmed;
use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Sanction;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ApiWriteTest extends TestCase
{
    use RefreshDatabase;

    private function account(): User
    {
        return User::factory()->create(['role_id' => Role::defaultUser()->id]);
    }

    private function bearer(User $user, array $abilities = ['reservations:read', 'reservations:write']): array
    {
        $this->app['auth']->forgetGuards(); // chaque requete reelle repart d'un garde vierge

        return ['Authorization' => 'Bearer ' . $user->createToken('test', $abilities)->plainTextToken];
    }

    private function slot(array $attributes = [], array $resource = []): Slot
    {
        $resource = Resource::create($resource + ['name' => 'CDI' . random_int(1, 99999), 'open_time' => '00:00', 'close_time' => '23:59', 'closed_days' => []]);

        return Slot::create($attributes + [
            'resource_id' => $resource->id, 'name' => 'Creneau',
            'start_time' => '23:00', 'end_time' => '23:30', 'max_capacity' => 5,
        ]);
    }

    private function tomorrow(): string
    {
        return now()->addDay()->toDateString();
    }

    public function test_a_reservation_is_created_with_the_site_side_effects(): void
    {
        Mail::fake();
        $user = $this->account();
        $slot = $this->slot();

        $response = $this->postJson('/api/v1/reservations', ['slot_id' => $slot->id, 'date' => $this->tomorrow()], $this->bearer($user))
            ->assertCreated()->assertJsonPath('data.date', $this->tomorrow())->assertJsonPath('data.slot.id', $slot->id);

        $id = $response->json('data.id');
        $response->assertHeader('Location', route('api.v1.reservations.show', $id));
        $this->assertSame($user->id, Reservation::findOrFail($id)->user_id);
        Mail::assertSent(ReservationConfirmed::class, fn ($mail) => $mail->hasTo($user->email));
        $this->assertTrue(ActivityLog::where('action', 'Réservation créée (API)')->where('user_id', $user->id)->exists());
    }

    public function test_writing_needs_the_write_ability(): void
    {
        $user = $this->account();
        $slot = $this->slot();
        $reservation = Reservation::create(['user_id' => $user->id, 'slot_id' => $slot->id, 'reservation_date' => $this->tomorrow()]);
        $readOnly = $this->bearer($user, ['reservations:read', 'resources:read']);

        $this->postJson('/api/v1/reservations', ['slot_id' => $slot->id, 'date' => $this->tomorrow()], $readOnly)
            ->assertStatus(403)->assertJsonPath('error.code', 'forbidden');
        $this->deleteJson("/api/v1/reservations/{$reservation->id}", [], $this->bearer($user, ['reservations:read']))->assertStatus(403);
        $this->assertSame(1, Reservation::count());
    }

    public function test_the_body_is_validated(): void
    {
        $headers = $this->bearer($this->account());

        $this->postJson('/api/v1/reservations', [], $headers)->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['slot_id', 'date']]]);
        $this->postJson('/api/v1/reservations', ['slot_id' => 99999, 'date' => $this->tomorrow()], $headers)->assertStatus(422);
        $this->postJson('/api/v1/reservations', ['slot_id' => $this->slot()->id, 'date' => '27/10/2026'], $headers)->assertStatus(422);
    }

    public function test_refusals_use_the_service_codes_and_matching_statuses(): void
    {
        $user = $this->account();
        $slot = $this->slot();
        $headers = fn () => $this->bearer($user);

        // doublon : la meme demande deux fois ne cree qu'une reservation
        $this->postJson('/api/v1/reservations', ['slot_id' => $slot->id, 'date' => $this->tomorrow()], $headers())->assertCreated();
        $this->postJson('/api/v1/reservations', ['slot_id' => $slot->id, 'date' => $this->tomorrow()], $headers())
            ->assertStatus(409)->assertJsonPath('error.code', 'already_booked');
        $this->assertSame(1, Reservation::count());

        // limite par jour : regle non respectee
        Role::defaultUser()->update(['max_reservations_day' => 1]);
        $this->postJson('/api/v1/reservations', ['slot_id' => $this->slot()->id, 'date' => $this->tomorrow()], $this->bearer($user->fresh()))
            ->assertStatus(422)->assertJsonPath('error.code', 'daily_limit');
        Role::defaultUser()->update(['max_reservations_day' => -1]);

        // date passee
        $this->postJson('/api/v1/reservations', ['slot_id' => $slot->id, 'date' => now()->subDay()->toDateString()], $this->bearer($user->fresh()))
            ->assertStatus(422)->assertJsonPath('error.code', 'date_past');

        // creneau complet
        $full = $this->slot(['max_capacity' => 1]);
        Reservation::create(['user_id' => $this->account()->id, 'slot_id' => $full->id, 'reservation_date' => $this->tomorrow()]);
        $this->postJson('/api/v1/reservations', ['slot_id' => $full->id, 'date' => $this->tomorrow()], $this->bearer($user->fresh()))
            ->assertStatus(409)->assertJsonPath('error.code', 'slot_full');
    }

    public function test_a_banned_account_cannot_book(): void
    {
        $user = $this->account();
        Sanction::create(['user_id' => $user->id, 'admin_id' => $user->id, 'type' => 'ban', 'reason' => 'test', 'banned_until' => null]);

        $this->postJson('/api/v1/reservations', ['slot_id' => $this->slot()->id, 'date' => $this->tomorrow()], $this->bearer($user))
            ->assertStatus(403)->assertJsonPath('error.code', 'banned');
        $this->assertSame(0, Reservation::count());
    }

    public function test_the_capacity_cannot_be_exceeded_through_the_api(): void
    {
        $slot = $this->slot(['max_capacity' => 2]);
        $codes = [];
        foreach (range(1, 4) as $i) {
            $codes[] = $this->postJson('/api/v1/reservations', ['slot_id' => $slot->id, 'date' => $this->tomorrow()], $this->bearer($this->account()))->status();
        }

        $this->assertSame([201, 201, 409, 409], $codes);
        $this->assertSame(2, Reservation::count());
    }

    public function test_a_reservation_is_cancelled_and_the_student_is_notified(): void
    {
        Mail::fake();
        $user = $this->account();
        $reservation = Reservation::create(['user_id' => $user->id, 'slot_id' => $this->slot()->id, 'reservation_date' => $this->tomorrow()]);

        $this->deleteJson("/api/v1/reservations/{$reservation->id}", [], $this->bearer($user))->assertNoContent();

        $this->assertSame(0, Reservation::count());
        Mail::assertSent(ReservationCancelled::class);
    }

    public function test_the_cancellation_rules_of_the_site_apply(): void
    {
        $user = $this->account();
        $started = $this->slot(['start_time' => '00:00', 'end_time' => '00:01']);
        $today = Reservation::create(['user_id' => $user->id, 'slot_id' => $started->id, 'reservation_date' => now()->toDateString()]);

        $this->deleteJson("/api/v1/reservations/{$today->id}", [], $this->bearer($user))
            ->assertStatus(409)->assertJsonPath('error.code', 'slot_started');

        Role::defaultUser()->update(['cancel_deadline_minutes' => 60 * 24 * 7]);
        $soon = Reservation::create(['user_id' => $user->id, 'slot_id' => $this->slot()->id, 'reservation_date' => $this->tomorrow()]);
        $this->deleteJson("/api/v1/reservations/{$soon->id}", [], $this->bearer($user->fresh()))
            ->assertStatus(409)->assertJsonPath('error.code', 'cancel_deadline');
        $this->assertSame(2, Reservation::count());
    }

    public function test_another_users_reservation_cannot_be_read_or_cancelled(): void
    {
        $owner = $this->account();
        $intruder = $this->account();
        $reservation = Reservation::create(['user_id' => $owner->id, 'slot_id' => $this->slot()->id, 'reservation_date' => $this->tomorrow()]);

        $this->getJson("/api/v1/reservations/{$reservation->id}", $this->bearer($intruder))->assertStatus(404)->assertJsonPath('error.code', 'not_found');
        $this->deleteJson("/api/v1/reservations/{$reservation->id}", [], $this->bearer($intruder))->assertStatus(404);
        $this->assertSame(1, Reservation::count());
        $this->getJson("/api/v1/reservations/{$reservation->id}", $this->bearer($owner))->assertOk()->assertJsonPath('data.id', $reservation->id);
    }

    public function test_writes_have_their_own_rate_limit(): void
    {
        $user = $this->account();
        $headers = $this->bearer($user);
        $statuses = [];
        for ($i = 0; $i < 31; $i++) {
            $statuses[] = $this->postJson('/api/v1/reservations', [], $headers)->status();
        }

        $this->assertSame(422, $statuses[29]);
        $this->assertSame(429, $statuses[30]);
    }
}
