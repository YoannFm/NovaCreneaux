<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Page;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class ActivityLoggingTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private function actions(): array
    {
        return ActivityLog::orderBy('id')->pluck('context', 'action')->all();
    }

    private function resource(): Resource
    {
        return Resource::create(['name' => 'Salle B', 'is_active' => true, 'open_time' => '08:00', 'close_time' => '18:00']);
    }

    public function test_models_log_their_creation_modification_and_deletion_with_the_right_gender(): void
    {
        $this->actingAs($admin = $this->admin());
        ActivityLog::query()->delete();

        $resource = $this->resource();
        $resource->update(['name' => 'Salle C']);
        $slot = $resource->slots()->create(['name' => 'Matin', 'start_time' => '08:00', 'end_time' => '10:00', 'max_capacity' => 2, 'is_active' => true]);
        $slot->delete();
        $resource->delete();

        $this->assertSame([
            'Ressource créée' => 'Salle B', 'Ressource modifiée' => 'Salle C', 'Créneau créé' => 'Salle C - Matin',
            'Créneau supprimé' => 'Salle C - Matin', 'Ressource supprimée' => 'Salle C',
        ], $this->actions());
        $this->assertSame($admin->id, ActivityLog::first()->user_id);
    }

    public function test_saving_without_changes_logs_nothing(): void
    {
        $this->actingAs($this->admin());
        $resource = $this->resource();
        ActivityLog::query()->delete();

        $resource->update(['name' => $resource->name]);

        $this->assertSame(0, ActivityLog::count());
    }

    public function test_roles_and_pages_are_logged(): void
    {
        $this->actingAs($this->admin());
        ActivityLog::query()->delete();

        $role = Role::create(['name' => 'tuteur', 'label' => 'Tuteur', 'color' => '#3b7ddd']);
        Page::create(['title' => 'Règlement', 'slug' => 'reglement', 'content' => 'x']);
        $role->delete();

        $this->assertSame(['Rôle créé' => 'Tuteur', 'Page créée' => 'Règlement', 'Rôle supprimé' => 'Tuteur'], $this->actions());
    }

    public function test_an_admin_action_through_the_interface_is_logged_exactly_once(): void
    {
        $admin = $this->admin();
        ActivityLog::query()->delete();

        $this->actingAs($admin)->post(route('admin.resources.store'), ['name' => 'Salle D', 'open_time' => '08:00', 'close_time' => '18:00'])->assertSessionHasNoErrors();

        $this->assertSame(1, ActivityLog::where('action', 'Ressource créée')->count());
    }
}
