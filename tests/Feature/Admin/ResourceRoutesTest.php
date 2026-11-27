<?php

namespace Tests\Feature\Admin;

use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Adresses des ressources et de leurs creneaux dans l'administration. */
class ResourceRoutesTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private function resource(string $name): Resource
    {
        return Resource::create(['name' => $name, 'is_active' => true, 'open_time' => '00:00', 'close_time' => '23:59', 'closed_days' => []]);
    }

    public function test_a_resource_has_no_detail_page(): void
    {
        $this->actingAs($this->admin())->get('/admin/resources/' . $this->resource('CDI')->id)->assertStatus(405)->assertSee('Adresse incorrecte');
    }

    public function test_a_slot_is_only_reached_through_its_own_resource(): void
    {
        $cdi = $this->resource('CDI');
        $salle = $this->resource('Salle');
        $slot = $cdi->slots()->create(['name' => 'Midi', 'start_time' => '12:00', 'end_time' => '13:00', 'max_capacity' => 24, 'is_active' => true]);
        $this->actingAs($this->admin());

        $this->get(route('admin.slots.edit', [$salle, $slot]))->assertNotFound();
        $this->put(route('admin.slots.update', [$salle, $slot]), ['name' => 'Midi', 'start_time' => '12:00', 'end_time' => '13:00', 'max_capacity' => 5])->assertNotFound();
        $this->delete(route('admin.slots.destroy', [$salle, $slot]))->assertNotFound();

        $this->assertSame(24, $slot->fresh()->max_capacity);
        $this->assertNull($slot->fresh()->deleted_at);
        $this->get(route('admin.slots.edit', [$cdi, $slot]))->assertOk();
    }
}
