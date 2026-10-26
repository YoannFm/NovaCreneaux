<?php

namespace Tests\Feature\Admin;

use App\Models\NavItem;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserAdminFormsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id'), 'is_admin' => true]);
    }

    private function student(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role_id' => Role::defaultUser()->id]);
    }

    private function bulk(string $action, array $ids)
    {
        return $this->post(route('admin.users.bulk'), ['action' => $action, 'ids' => $ids]);
    }

    /** Un <form> ne peut pas en contenir un autre : le navigateur ignorerait le second et fusionnerait ses champs. */
    private function assertNoNestedForms(string $html, string $page): void
    {
        $depth = 0;
        preg_match_all('#<form\b|</form>#i', $html, $matches);
        foreach ($matches[0] as $tag) {
            $depth += str_starts_with($tag, '</') ? -1 : 1;
            $this->assertLessThanOrEqual(1, $depth, "Formulaires imbriques dans {$page}");
        }
    }

    // ---- les deux bugs signales --------------------------------------------------------------

    public function test_a_password_can_be_set_without_any_confirmation_field(): void
    {
        $role = Role::defaultUser();

        $this->actingAs($this->admin())->post(route('admin.users.store'), [
            'name' => 'Nouvel Eleve', 'email' => 'nouveau@example.com', 'role_id' => $role->id, 'password' => 'MotDePasse123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('MotDePasse123', User::where('email', 'nouveau@example.com')->value('password')));
    }

    public function test_the_creation_form_preselects_the_default_role_not_the_administrator(): void
    {
        $html = $this->actingAs($this->admin())->get(route('admin.users.create'))->assertOk()->getContent();

        preg_match('#<option value="(\d+)"\s+selected#', $html, $match);
        $this->assertSame(Role::defaultUser()->id, (int) ($match[1] ?? 0));
    }

    public function test_editing_a_user_changes_the_password_without_confirmation_and_keeps_the_account(): void
    {
        $student = $this->student();

        $this->actingAs($this->admin())->put(route('admin.users.update', $student), [
            'name' => 'Nom Modifie', 'email' => $student->email, 'role_id' => $student->role_id, 'password' => 'AutreMotDePasse1', 'is_active' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $student->refresh();
        $this->assertSame('Nom Modifie', $student->name);
        $this->assertTrue(Hash::check('AutreMotDePasse1', $student->password));
    }

    public function test_the_edit_pages_have_no_nested_forms_so_saving_never_deletes(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $role = Role::create(['name' => 'prof', 'label' => 'Professeur']);
        $nav = NavItem::create(['label' => 'Lien', 'type' => 'external', 'url' => 'https://example.com', 'icon' => 'bi-link', 'target' => '_self', 'is_active' => true, 'admin_only' => false, 'position' => 9]);
        $this->actingAs($admin);

        foreach ([
            'utilisateur' => route('admin.users.edit', $student),
            'role' => route('admin.roles.edit', $role),
            'navigation' => route('admin.nav.edit', $nav->id),
        ] as $page => $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertNoNestedForms($html, $page);
            $this->assertMatchesRegularExpression('/<button[^>]*form="delete-/', $html, "Bouton de suppression de {$page}");
        }
    }

    public function test_deleting_from_the_edit_page_returns_to_the_list_not_to_the_deleted_page(): void
    {
        $student = $this->student();
        $role = Role::create(['name' => 'prof', 'label' => 'Professeur']);
        $this->actingAs($this->admin());

        $this->from(route('admin.users.edit', $student))->delete(route('admin.users.destroy', $student))
            ->assertRedirect(route('admin.users.index'))->assertSessionHas('success', 'Utilisateur supprimé.');
        $this->assertFalse(User::whereKey($student->id)->exists());

        $this->from(route('admin.roles.edit', $role))->delete(route('admin.roles.destroy', $role))
            ->assertRedirect(route('admin.roles.index'));
        $this->assertFalse(Role::whereKey($role->id)->exists());
    }

    public function test_every_admin_page_is_free_of_nested_forms(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        foreach (['admin.users.index', 'admin.users.create', 'admin.roles.index', 'admin.roles.create', 'admin.resources.create', 'admin.nav.create', 'admin.pages.create', 'admin.settings.security', 'admin.api-tokens.index'] as $route) {
            $this->assertNoNestedForms($this->get(route($route))->assertOk()->getContent(), $route);
        }
    }

    // ---- selection multiple ------------------------------------------------------------------------

    public function test_the_list_offers_checkboxes_and_the_action_bar(): void
    {
        $this->student(['name' => 'Eleve Liste']);

        $this->actingAs($this->admin())->get(route('admin.users.index'))->assertOk()
            ->assertSee('id="bulk-all"', false)->assertSee('class="form-check-input bulk-item"', false)
            ->assertSee('Réinitialiser MDP')->assertSee('Exporter CSV')->assertSee('sélectionné(s)');
    }

    public function test_only_administrators_can_use_bulk_actions(): void
    {
        $victim = $this->student();

        $this->post(route('admin.users.bulk'), ['action' => 'delete', 'ids' => [$victim->id]])->assertRedirect(route('login'));
        $this->actingAs($this->student())->post(route('admin.users.bulk'), ['action' => 'delete', 'ids' => [$victim->id]])->assertRedirect('/');
        $this->assertTrue(User::whereKey($victim->id)->exists());
    }

    public function test_activate_and_deactivate_only_touch_the_selection(): void
    {
        $this->actingAs($this->admin());
        [$a, $b, $untouched] = [$this->student(), $this->student(), $this->student()];

        $this->bulk('deactivate', [$a->id, $b->id])->assertSessionHas('success');
        $this->assertFalse($a->fresh()->is_active);
        $this->assertFalse($b->fresh()->is_active);
        $this->assertTrue($untouched->fresh()->is_active);

        $this->bulk('activate', [$a->id])->assertSessionHas('success');
        $this->assertTrue($a->fresh()->is_active);
        $this->assertFalse($b->fresh()->is_active);
    }

    public function test_the_acting_admin_is_never_deactivated_or_deleted_by_a_bulk_action(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $this->actingAs($admin);

        $this->bulk('deactivate', [$admin->id, $student->id])->assertSessionHas('success');
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertFalse($student->fresh()->is_active);

        $this->bulk('delete', [$admin->id])->assertSessionHas('success');
        $this->assertTrue(User::whereKey($admin->id)->exists());
    }

    public function test_bulk_delete_removes_the_accounts_and_their_reservations(): void
    {
        $this->actingAs($this->admin());
        $resource = Resource::create(['name' => 'CDI', 'open_time' => '00:00', 'close_time' => '23:59', 'closed_days' => []]);
        $slot = Slot::create(['resource_id' => $resource->id, 'name' => 'A', 'start_time' => '10:00', 'end_time' => '11:00', 'max_capacity' => 5]);
        [$gone, $kept] = [$this->student(), $this->student()];
        Reservation::create(['user_id' => $gone->id, 'slot_id' => $slot->id, 'reservation_date' => now()->addDay()->toDateString()]);

        $this->bulk('delete', [$gone->id])->assertSessionHas('success');

        $this->assertFalse(User::whereKey($gone->id)->exists());
        $this->assertTrue(User::whereKey($kept->id)->exists());
        $this->assertSame(0, Reservation::count());
    }

    public function test_reset_password_sends_a_link_to_active_accounts_only(): void
    {
        Notification::fake();
        $this->actingAs($this->admin());
        [$active, $inactive] = [$this->student(), $this->student(['is_active' => false])];

        $this->bulk('reset_password', [$active->id, $inactive->id])->assertSessionHas('success');

        Notification::assertSentTo($active, ResetPassword::class);
        Notification::assertNotSentTo($inactive, ResetPassword::class);
    }

    public function test_export_contains_only_the_selected_users_and_neutralises_formulas(): void
    {
        $this->actingAs($this->admin());
        $chosen = $this->student(['name' => '=cmd|calc', 'email' => 'choisi@example.com']);
        $this->student(['name' => 'Pas Choisi', 'email' => 'absent@example.com']);

        $response = $this->bulk('export', [$chosen->id])->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->getContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('choisi@example.com', $csv);
        $this->assertStringNotContainsString('absent@example.com', $csv);
        $this->assertStringContainsString("\"'=cmd|calc\"", $csv);
    }

    public function test_bulk_requests_are_validated(): void
    {
        $student = $this->student();
        $this->actingAs($this->admin());

        $this->bulk('delete', [])->assertSessionHasErrors('ids');
        $this->bulk('explode', [$student->id])->assertSessionHasErrors('action');
        $this->bulk('delete', [999999])->assertSessionHasErrors('ids.0');
        $this->assertTrue(User::whereKey($student->id)->exists());
    }
}
