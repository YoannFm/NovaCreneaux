<?php

namespace Tests\Feature\Api;

use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ApiReadTest extends TestCase
{
    use RefreshDatabase;

    private const READ_ALL = ['resources:read', 'reservations:read'];

    private function service(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role_id' => Role::defaultUser()->id]);
    }

    private function bearer(User $user, array $abilities = self::READ_ALL, $expires = null): array
    {
        return ['Authorization' => 'Bearer ' . $user->createToken('test', $abilities, $expires)->plainTextToken];
    }

    private function resource(string $name = 'CDI', array $attributes = []): Resource
    {
        return Resource::create($attributes + ['name' => $name, 'open_time' => '08:00', 'close_time' => '23:59', 'closed_days' => [6, 7]]);
    }

    private function slot(Resource $resource, array $attributes = []): Slot
    {
        return Slot::create($attributes + [
            'resource_id' => $resource->id, 'name' => 'Creneau',
            'start_time' => '23:00', 'end_time' => '23:30', 'max_capacity' => 5,
        ]);
    }

    private function nextWeekday(): string
    {
        $date = now()->addDay();
        while ($date->isoWeekday() > 5) {
            $date = $date->addDay();
        }

        return $date->toDateString();
    }

    // ---- authentification et erreurs ----------------------------------------------------

    public function test_meta_is_public_and_describes_the_api(): void
    {
        $this->getJson('/api/v1/meta')->assertOk()->assertJsonPath('data.api_version', 'v1')
            ->assertJsonStructure(['data' => ['app_name', 'timezone', 'server_time']]);
    }

    public function test_requests_without_a_valid_token_get_a_401_in_the_standard_format(): void
    {
        $this->getJson('/api/v1/resources')->assertStatus(401)->assertJsonPath('error.code', 'unauthenticated');
        $this->getJson('/api/v1/resources', ['Authorization' => 'Bearer ncr_wrong'])->assertStatus(401);
    }

    public function test_a_session_cookie_does_not_authenticate_the_api(): void
    {
        $this->actingAs($this->service())->getJson('/api/v1/resources')->assertStatus(401);
    }

    public function test_an_expired_token_is_refused(): void
    {
        $user = $this->service();

        $this->getJson('/api/v1/resources', $this->bearer($user, self::READ_ALL, now()->subMinute()))->assertStatus(401);
    }

    public function test_a_token_without_the_ability_gets_a_403(): void
    {
        $headers = $this->bearer($this->service(), ['reservations:read']);

        $this->getJson('/api/v1/resources', $headers)->assertStatus(403)->assertJsonPath('error.code', 'forbidden');
        $this->getJson('/api/v1/reservations', $headers)->assertOk();
    }

    public function test_a_deactivated_account_is_refused(): void
    {
        $user = $this->service();
        $headers = $this->bearer($user);
        $user->update(['is_active' => false]);

        $this->getJson('/api/v1/me', $headers)->assertStatus(403)->assertJsonPath('error.code', 'account_disabled');
    }

    public function test_maintenance_mode_answers_503_in_json(): void
    {
        Setting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => '1']);
        cache()->forget('setting_maintenance_mode');

        $this->getJson('/api/v1/me', $this->bearer($this->service()))->assertStatus(503)->assertJsonPath('error.code', 'maintenance');
    }

    public function test_unknown_api_routes_return_a_json_404(): void
    {
        $this->getJson('/api/v1/nothing-here')->assertStatus(404)->assertJsonPath('error.code', 'not_found');
    }

    public function test_the_ip_rate_limit_answers_429(): void
    {
        RateLimiter::clear('api-ip');
        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/v1/meta');
        }

        $this->getJson('/api/v1/meta')->assertStatus(429)->assertJsonPath('error.code', 'too_many_requests')->assertHeader('Retry-After');
    }

    public function test_me_describes_the_token_owner(): void
    {
        $user = $this->service(['name' => 'Borne CDI']);

        $this->getJson('/api/v1/me', $this->bearer($user, ['resources:read']))
            ->assertOk()->assertJsonPath('data.name', 'Borne CDI')->assertJsonPath('data.role.name', 'user')
            ->assertJsonPath('data.abilities', ['resources:read']);
    }

    // ---- ressources et creneaux -----------------------------------------------------------

    public function test_only_active_resources_and_slots_are_exposed(): void
    {
        $open = $this->resource('Salle ouverte');
        $this->slot($open, ['name' => 'Actif']);
        $this->slot($open, ['name' => 'Inactif', 'is_active' => false]);
        $closed = $this->resource('Salle fermee', ['is_active' => false]);
        $headers = $this->bearer($this->service());

        $this->getJson('/api/v1/resources', $headers)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Salle ouverte');
        $this->getJson("/api/v1/resources/{$closed->id}", $headers)->assertStatus(404);
        $this->getJson("/api/v1/resources/{$open->id}/slots", $headers)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Actif')->assertJsonPath('data.0.start_time', '23:00')->assertJsonPath('data.0.capacity', 5);
        $this->getJson("/api/v1/resources/{$open->id}", $headers)->assertOk()->assertJsonPath('data.closed_days', [6, 7]);
    }

    public function test_the_resource_list_is_filtered_and_paginated(): void
    {
        $this->resource('Salle A');
        $this->resource('Salle B');
        $this->resource('Autre');
        $headers = $this->bearer($this->service());

        $this->getJson('/api/v1/resources?search=Salle&per_page=1', $headers)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/resources?per_page=500', $headers)->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')->assertJsonStructure(['error' => ['details' => ['per_page']]]);
    }

    // ---- disponibilites et regles -------------------------------------------------------------

    public function test_availability_gives_free_spots_and_the_booking_verdict(): void
    {
        $resource = $this->resource();
        $slot = $this->slot($resource, ['max_capacity' => 1]);
        $date = $this->nextWeekday();
        Reservation::create(['user_id' => $this->service()->id, 'slot_id' => $slot->id, 'reservation_date' => $date]);
        $headers = $this->bearer($this->service());

        $this->getJson("/api/v1/availability?date={$date}", $headers)->assertOk()
            ->assertJsonPath('data.resources.0.slots.0.free_spots', 0)
            ->assertJsonPath('data.resources.0.slots.0.bookable', false)
            ->assertJsonPath('data.resources.0.slots.0.reason_code', 'slot_full');

        $other = $this->slot($resource, ['name' => 'Libre', 'start_time' => '22:00', 'end_time' => '22:30']);
        $this->getJson("/api/v1/availability?date={$date}&resource_id={$resource->id}", $headers)->assertOk()
            ->assertJsonPath('data.resources.0.slots.0.id', $other->id)
            ->assertJsonPath('data.resources.0.slots.0.bookable', true)
            ->assertJsonPath('data.resources.0.slots.0.reason_code', null);
    }

    public function test_availability_reports_a_closed_day_with_its_code(): void
    {
        $this->slot($this->resource());
        $saturday = now()->next('Saturday')->toDateString();

        $this->getJson("/api/v1/availability?date={$saturday}", $this->bearer($this->service()))->assertOk()
            ->assertJsonPath('data.resources.0.slots.0.reason_code', 'resource_closed_day');
    }

    public function test_availability_requires_a_valid_date(): void
    {
        $headers = $this->bearer($this->service());

        $this->getJson('/api/v1/availability', $headers)->assertStatus(422);
        $this->getJson('/api/v1/availability?date=25/12/2026', $headers)->assertStatus(422);
    }

    public function test_rules_show_the_role_limits_with_null_for_unlimited(): void
    {
        Role::defaultUser()->update(['max_reservations_day' => 2, 'max_reservations_week' => -1, 'cancel_deadline_minutes' => 30, 'advance_days' => 14]);

        $this->getJson('/api/v1/rules', $this->bearer($this->service()))->assertOk()->assertExactJson(['data' => [
            'role' => ['name' => 'user', 'label' => Role::defaultUser()->label],
            'max_reservations_per_day' => 2,
            'max_reservations_per_week' => null,
            'cancel_deadline_minutes' => 30,
            'advance_days' => 14,
        ]]);
    }

    // ---- reservations : jamais celles des autres -------------------------------------------------

    public function test_reservations_are_limited_to_the_token_owner(): void
    {
        $slot = $this->slot($this->resource());
        $mine = $this->service();
        $other = $this->service();
        Reservation::create(['user_id' => $mine->id, 'slot_id' => $slot->id, 'reservation_date' => now()->addDay()->toDateString()]);
        Reservation::create(['user_id' => $other->id, 'slot_id' => $slot->id, 'reservation_date' => now()->addDay()->toDateString()]);

        $this->getJson('/api/v1/reservations', $this->bearer($mine))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.resource.name', 'CDI')->assertJsonMissingPath('data.0.user')
            ->assertJsonMissing(['email' => $other->email]);
    }

    public function test_reservations_can_be_filtered_by_date(): void
    {
        $slot = $this->slot($this->resource());
        $user = $this->service();
        foreach ([1, 5, 9] as $days) {
            Reservation::create(['user_id' => $user->id, 'slot_id' => $slot->id, 'reservation_date' => now()->addDays($days)->toDateString()]);
        }
        $from = now()->addDays(2)->toDateString();
        $to = now()->addDays(6)->toDateString();

        $this->getJson("/api/v1/reservations?from={$from}&to={$to}", $this->bearer($user))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.date', now()->addDays(5)->toDateString());
        $this->getJson("/api/v1/reservations?from={$to}&to={$from}", $this->bearer($user))->assertStatus(422);
    }
}
