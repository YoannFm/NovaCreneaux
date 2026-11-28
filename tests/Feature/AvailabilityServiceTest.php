<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Slot;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_spots_are_counted_per_slot_and_date(): void
    {
        $resource = Resource::create(['name' => 'CDI', 'open_time' => '00:00', 'close_time' => '23:59', 'closed_days' => []]);
        $small = Slot::create(['resource_id' => $resource->id, 'name' => 'A', 'start_time' => '10:00', 'end_time' => '11:00', 'max_capacity' => 2]);
        $big = Slot::create(['resource_id' => $resource->id, 'name' => 'B', 'start_time' => '11:00', 'end_time' => '12:00', 'max_capacity' => 10]);
        $monday = now()->addDay()->toDateString();
        $tuesday = now()->addDays(2)->toDateString();

        User::factory()->count(2)->create()->each(fn ($user) => Reservation::create(['user_id' => $user->id, 'slot_id' => $small->id, 'reservation_date' => $monday]));
        Reservation::create(['user_id' => User::first()->id, 'slot_id' => $big->id, 'reservation_date' => $tuesday]);

        $free = app(AvailabilityService::class)->freeSpots([$small, $big], [$monday, $tuesday]);

        $this->assertSame(0, $free[$small->id][$monday]);
        $this->assertSame(2, $free[$small->id][$tuesday]);
        $this->assertSame(10, $free[$big->id][$monday]);
        $this->assertSame(9, $free[$big->id][$tuesday]);
    }

    public function test_no_slot_or_no_date_gives_an_empty_result(): void
    {
        $this->assertSame([], app(AvailabilityService::class)->freeSpots([], ['2026-10-27']));
    }

    public function test_the_booking_page_still_renders_with_slots(): void
    {
        $resource = Resource::create(['name' => 'CDI', 'open_time' => '00:00', 'close_time' => '23:59', 'closed_days' => []]);
        Slot::create(['resource_id' => $resource->id, 'name' => 'Recre', 'start_time' => '10:00', 'end_time' => '10:15', 'max_capacity' => 5]);
        $user = User::factory()->create(['role_id' => Role::defaultUser()->id]);

        $this->actingAs($user)->get(route('reservations.index'))->assertOk()->assertSee('Recre');
    }
}
