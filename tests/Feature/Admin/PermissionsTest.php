<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Sanction;
use App\Models\User;
use App\Support\AdminNavigation;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    public function test_students_never_enter_the_administration(): void
    {
        $this->actingAs($this->student())->get(route('admin.dashboard'))->assertRedirect('/');
        $this->get(route('admin.users.index'))->assertRedirect('/');
    }

    public function test_a_role_without_permissions_is_not_staff(): void
    {
        $this->actingAs($this->staff([]))->get(route('admin.dashboard'))->assertRedirect('/');
    }

    public function test_staff_reach_only_the_sections_they_were_granted(): void
    {
        $this->actingAs($this->staff([Permission::USERS]));

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.users.index'))->assertOk();
        foreach (['admin.resources.index', 'admin.pages.index', 'admin.roles.index', 'admin.settings.general', 'admin.update.index', 'admin.api-tokens.index', 'admin.logs.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('admin.dashboard'))->assertSessionHas('error');
        }
    }

    public function test_every_menu_entry_matches_the_access_of_its_route(): void
    {
        $everything = (new \ReflectionClass(Permission::class))->getConstants();
        $everyLink = app(AdminNavigation::class)->links($this->admin());
        $this->assertNotEmpty($everyLink);

        $profiles = [$this->admin(), $this->staff(array_values($everything), 'tout')];
        foreach (Permission::all() as $key => $label) {
            $profiles[] = $this->staff([$key], 'seul-' . str_replace('.', '-', $key));
        }

        foreach ($profiles as $user) {
            $visible = array_column(app(AdminNavigation::class)->links($user), 'route');
            foreach ($everyLink as $link) {
                $response = $this->actingAs($user)->get($link['url']);
                in_array($link['route'], $visible, true)
                    ? $response->assertOk()
                    : $response->assertRedirect(route('admin.dashboard'));
            }
        }
    }

    public function test_the_menu_only_lists_what_the_user_may_open(): void
    {
        $this->actingAs($this->staff([Permission::PAGES, Permission::APPEARANCE]))->get(route('admin.dashboard'))
            ->assertOk()->assertSee(route('admin.pages.index'))->assertSee(route('admin.themes.index'))
            ->assertDontSee(route('admin.update.index'))->assertDontSee(route('admin.roles.index'))->assertDontSee(route('admin.settings.security'));
    }

    public function test_staff_cannot_view_manage_or_promote_administrators(): void
    {
        $staff = $this->staff([Permission::USERS, Permission::SANCTIONS]);
        $admin = $this->admin();
        $student = $this->student();
        $adminRoleId = Role::where('name', 'admin')->value('id');

        $this->actingAs($staff);
        $this->get(route('admin.users.edit', $admin))->assertForbidden();
        $this->put(route('admin.users.update', $admin), ['name' => 'X', 'email' => 'x@e.fr', 'role_id' => $adminRoleId])->assertForbidden();
        $this->patch(route('admin.users.toggle', $admin))->assertForbidden();
        $this->delete(route('admin.users.destroy', $admin))->assertForbidden();
        $this->post(route('admin.sanctions.store', $admin), ['type' => 'warning', 'reason' => 'test'])->assertForbidden();
        $this->put(route('admin.users.update', $student), ['name' => 'Eleve', 'email' => $student->email, 'role_id' => $adminRoleId])->assertForbidden();
        $this->post(route('admin.users.store'), ['name' => 'Nouveau', 'email' => 'nouveau@e.fr', 'role_id' => $adminRoleId])->assertForbidden();
        $this->assertSame($student->role_id, $student->fresh()->role_id);
        $this->assertDatabaseMissing('users', ['email' => 'nouveau@e.fr']);

        $this->put(route('admin.users.update', $student), ['name' => 'Eleve Modifié', 'email' => $student->email, 'role_id' => $student->role_id])->assertRedirect(route('admin.users.index'));
        $this->post(route('admin.sanctions.store', $student), ['type' => 'warning', 'reason' => 'test'])->assertSessionHasNoErrors();
        $this->assertSame('Eleve Modifié', $student->fresh()->name);
        $this->assertSame(1, Sanction::where('user_id', $student->id)->count());
    }

    public function test_staff_cannot_manage_other_staff_nor_see_privileged_roles_in_the_forms(): void
    {
        $staff = $this->staff([Permission::USERS]);
        $colleague = $this->staff([Permission::PAGES], 'collegue');

        $this->actingAs($staff)->get(route('admin.users.edit', $colleague))->assertForbidden();
        $this->get(route('admin.users.create'))->assertOk()
            ->assertDontSee('<option value="' . $colleague->role_id . '"', false)
            ->assertDontSee('<option value="' . Role::where('name', 'admin')->value('id') . '"', false)
            ->assertSee('<option value="' . Role::defaultUser()->id . '"', false);
    }

    public function test_bulk_actions_skip_accounts_the_actor_cannot_manage(): void
    {
        $staff = $this->staff([Permission::USERS]);
        $admin = $this->admin();
        $student = $this->student();

        $this->actingAs($staff)->post(route('admin.users.bulk'), ['action' => 'deactivate', 'ids' => [$admin->id, $student->id]])->assertSessionHas('success');
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertFalse($student->fresh()->is_active);

        $this->post(route('admin.users.bulk'), ['action' => 'delete', 'ids' => [$admin->id]])->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_the_role_form_saves_known_permissions_only(): void
    {
        $payload = ['label' => 'Gestionnaire', 'color' => '#3b7ddd', 'max_reservations_day' => 2, 'max_reservations_week' => 5, 'cancel_deadline_minutes' => 30, 'advance_days' => 14];

        $this->actingAs($this->admin())
            ->post(route('admin.roles.store'), $payload + ['name' => 'gestionnaire', 'permissions' => [Permission::PAGES, Permission::USERS]])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.roles.index'));
        $role = Role::where('name', 'gestionnaire')->first();
        $this->assertEqualsCanonicalizing([Permission::PAGES, Permission::USERS], $role->permissions);

        $this->post(route('admin.roles.store'), $payload + ['name' => 'pirate', 'permissions' => ['settings.manage']])->assertSessionHasErrors('permissions.*');
        $this->assertDatabaseMissing('roles', ['name' => 'pirate']);

        $this->put(route('admin.roles.update', $role), $payload + ['permissions' => [Permission::LOGS]])->assertSessionHasNoErrors();
        $this->assertSame([Permission::LOGS], $role->fresh()->permissions);

        $this->put(route('admin.roles.update', $role), $payload + ['is_admin' => '1', 'permissions' => [Permission::LOGS]]);
        $this->assertNull($role->fresh()->permissions);
        $this->actingAs($this->admin())->get(route('admin.roles.edit', $role))->assertOk()->assertSee('Accès à l');
    }

    public function test_only_administrators_manage_roles(): void
    {
        $staff = $this->staff([Permission::USERS]);
        $this->actingAs($staff)->put(route('admin.roles.update', $staff->role), ['label' => 'Pirate', 'color' => '#000000', 'is_admin' => '1'])->assertRedirect(route('admin.dashboard'));
        $this->assertFalse($staff->role->fresh()->is_admin);
    }

    public function test_staff_are_not_locked_out_by_maintenance_mode(): void
    {
        app(\App\Services\SettingService::class)->set('maintenance_mode', '1');

        $this->actingAs($this->staff([Permission::PAGES]))->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($this->student())->get(route('admin.dashboard'))->assertStatus(503);
    }

    public function test_permission_helper_ignores_unknown_and_duplicate_keys(): void
    {
        $this->assertSame([Permission::USERS], Permission::only([Permission::USERS, Permission::USERS, 'settings.manage', 'x']));
    }

    public function test_user_helpers(): void
    {
        $admin = $this->admin();
        $staff = $this->staff([Permission::PAGES]);
        $student = $this->student();

        $this->assertTrue($admin->hasPermission(Permission::USERS));
        $this->assertTrue($staff->hasPermission(Permission::PAGES));
        $this->assertFalse($staff->hasPermission(Permission::USERS));
        $this->assertFalse($student->hasAdminAccess());
        $this->assertTrue($staff->hasAdminAccess());
        $this->assertTrue($admin->canManage($staff));
        $this->assertFalse($staff->canManage($admin));
        $this->assertTrue($staff->canManage($student));
    }
}
