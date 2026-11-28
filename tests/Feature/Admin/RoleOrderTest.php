<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class RoleOrderTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    public function test_the_script_the_list_depends_on_is_shipped(): void
    {
        $page = $this->actingAs($this->admin())->get(route('admin.roles.index'))->assertOk();

        preg_match('#src="[^"]*/(vendor/sortablejs/Sortable\.min\.js)"#', $page->getContent(), $match);
        $this->assertNotEmpty($match, 'La page des rôles doit charger Sortable.');
        $this->assertFileExists(public_path($match[1]));
    }

    public function test_the_first_role_of_the_list_gets_the_highest_power_and_the_list_is_sorted_by_it(): void
    {
        $admin = $this->admin();
        $roles = Role::orderBy('id')->pluck('id')->all();
        $this->assertGreaterThanOrEqual(2, count($roles));

        // le navigateur envoie la liste inversee : le dernier de la liste visible a le pouvoir 0
        $this->actingAs($admin)->postJson(route('admin.roles.reorder'), ['roles' => $roles])->assertOk();

        $listed = Role::orderByDesc('power')->pluck('id')->all();
        $this->assertSame(array_reverse($roles), $listed);
        $this->actingAs($admin)->get(route('admin.roles.index'))->assertOk();
    }

    public function test_only_administrators_reorder_roles(): void
    {
        $this->actingAs($this->student())->postJson(route('admin.roles.reorder'), ['roles' => [1, 2]])->assertRedirect('/');
    }
}
