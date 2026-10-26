<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultRoleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_without_role_get_the_matching_default_role(): void
    {
        $student = User::factory()->create(['role_id' => null, 'is_admin' => false]);
        $admin = User::factory()->create(['role_id' => null, 'is_admin' => true]);
        $other = Role::create(['name' => 'prof', 'label' => 'Professeur']);
        $teacher = User::factory()->create(['role_id' => $other->id]);

        (require database_path('migrations/2026_10_26_000001_assign_default_role_to_users_without_role.php'))->up();

        $this->assertSame(Role::defaultUser()->id, $student->fresh()->role_id);
        $this->assertSame(Role::where('name', 'admin')->value('id'), $admin->fresh()->role_id);
        $this->assertSame($other->id, $teacher->fresh()->role_id);
    }
}
