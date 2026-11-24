<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Support\Permission;
use App\Support\ReservationLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class ReservationLimitsSettingsTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private function userRole(): Role
    {
        return Role::defaultUser();
    }

    /** Formulaire complet d'un role : des nombres, aucun interrupteur "Illimite". */
    private function limits(array $override = []): array
    {
        return $override + ['max_reservations_day' => 2, 'max_reservations_week' => 5, 'cancel_deadline_minutes' => 30, 'advance_days' => 14];
    }

    private function save(array $roles)
    {
        return $this->from(route('admin.settings.reservations'))->post(route('admin.settings.reservations.update'), ['roles' => $roles]);
    }

    public function test_the_page_shows_one_card_per_role_with_its_values_and_its_account_count(): void
    {
        $admin = $this->admin();
        $this->student();
        $this->userRole()->update($this->limits(['max_reservations_week' => ReservationLimits::UNLIMITED]));
        $id = $this->userRole()->id;

        $page = $this->actingAs($admin)->get(route('admin.settings.reservations'))->assertOk();

        $page->assertSee($this->userRole()->label)->assertSee('1 compte')
            ->assertSee('name="roles[' . $id . '][max_reservations_day]"', false)
            ->assertSee('id="limit-' . $id . '-max_reservations_day" name="roles[' . $id . '][max_reservations_day]"', false)
            ->assertSee('Illimité')->assertSee('Toujours possible');
        $this->assertMatchesRegularExpression('/id="limit-' . $id . '-max_reservations_week"[^>]*value=""[^>]*disabled/s', $page->getContent());
        $this->assertMatchesRegularExpression('/id="limit-' . $id . '-max_reservations_week-unlimited"[^>]*checked/s', $page->getContent());
    }

    public function test_numbers_are_saved_and_a_checked_unlimited_switch_stores_minus_one(): void
    {
        $this->actingAs($this->admin());
        $role = $this->userRole();

        $this->save([$role->id => $this->limits([
            'max_reservations_week' => '',
            'unlimited' => ['max_reservations_week' => '1', 'cancel_deadline_minutes' => '1'],
        ])])->assertSessionHasNoErrors()->assertSessionHas('success');

        $role->refresh();
        $this->assertSame(2, $role->max_reservations_day);
        $this->assertSame(ReservationLimits::UNLIMITED, $role->max_reservations_week);
        $this->assertSame(ReservationLimits::UNLIMITED, $role->cancel_deadline_minutes, 'interrupteur coche : la valeur envoyee est ignoree');
        $this->assertSame(14, $role->advance_days);
    }

    public function test_out_of_range_or_missing_values_are_refused_and_nothing_is_saved(): void
    {
        $this->actingAs($this->admin());
        $role = $this->userRole();
        $role->update($this->limits());

        $this->save([$role->id => $this->limits(['max_reservations_day' => -5])])->assertSessionHasErrors("roles.{$role->id}.max_reservations_day");
        $this->save([$role->id => $this->limits(['max_reservations_day' => ''])])->assertSessionHasErrors("roles.{$role->id}.max_reservations_day");
        $this->save([$role->id => $this->limits(['advance_days' => 0])])->assertSessionHasErrors("roles.{$role->id}.advance_days");
        // "Sans limite" n'existe pas pour les jours a l'avance : l'interrupteur force n'est pas pris en compte
        $this->save([$role->id => $this->limits(['advance_days' => '', 'unlimited' => ['advance_days' => '1']])])
            ->assertSessionHasErrors("roles.{$role->id}.advance_days");

        $this->assertSame(2, $role->refresh()->max_reservations_day);
        $this->assertSame(14, $role->advance_days);
    }

    public function test_a_refused_form_keeps_what_was_typed_and_the_switches_as_sent(): void
    {
        $this->actingAs($this->admin());
        $role = $this->userRole();
        $role->update($this->limits(['max_reservations_week' => ReservationLimits::UNLIMITED]));

        $this->save([$role->id => $this->limits(['max_reservations_day' => 500, 'max_reservations_week' => 7])]);
        $page = $this->get(route('admin.settings.reservations'))->assertOk()
            ->assertSee('Rien n\'a été enregistré', false)->assertSee('value="500"', false)->assertSee('invalid-feedback', false);

        // En base la semaine est illimitee, mais l'interrupteur avait ete decoche : il reste decoche
        $this->assertMatchesRegularExpression('/id="limit-' . $role->id . '-max_reservations_week"[^>]*value="7"/s', $page->getContent());
        $this->assertDoesNotMatchRegularExpression('/id="limit-' . $role->id . '-max_reservations_week-unlimited"[^>]*checked/s', $page->getContent());
    }

    public function test_a_role_missing_from_the_form_keeps_its_limits(): void
    {
        $this->actingAs($this->admin());
        $other = Role::create(['name' => 'prof', 'label' => 'Professeur', 'color' => '#198754'] + $this->limits(['max_reservations_day' => 4]));

        $this->save([$this->userRole()->id => $this->limits()])->assertSessionHasNoErrors();

        $this->assertSame(4, $other->refresh()->max_reservations_day);
    }

    public function test_a_role_with_unlimited_limits_can_still_be_edited_from_the_roles_page(): void
    {
        $this->actingAs($this->admin());
        $role = Role::create(['name' => 'prof', 'label' => 'Professeur', 'color' => '#198754'] + $this->limits(['max_reservations_day' => ReservationLimits::UNLIMITED]));

        $this->get(route('admin.roles.edit', $role))->assertOk()
            ->assertDontSee('name="max_reservations_day"', false)
            ->assertSee('href="' . route('admin.settings.reservations') . '"', false);
        $this->put(route('admin.roles.update', $role), ['label' => 'Enseignant', 'color' => '#198754'])->assertSessionHasNoErrors();

        $role->refresh();
        $this->assertSame('Enseignant', $role->label);
        $this->assertSame(ReservationLimits::UNLIMITED, $role->max_reservations_day, 'la fiche du role ne touche plus aux limites');
    }

    public function test_a_new_role_starts_with_the_limits_of_the_default_role(): void
    {
        $this->actingAs($this->admin());
        $this->userRole()->update($this->limits(['max_reservations_day' => 3]));

        $this->post(route('admin.roles.store'), ['name' => 'stagiaire', 'label' => 'Stagiaire', 'color' => '#123456'])->assertSessionHasNoErrors();

        $this->assertSame(3, Role::where('name', 'stagiaire')->value('max_reservations_day'));
        $this->assertSame(14, Role::where('name', 'stagiaire')->value('advance_days'));
    }

    public function test_staff_who_manage_reservations_cannot_change_the_limits(): void
    {
        $role = $this->userRole();
        $role->update($this->limits());
        $this->actingAs($this->staff([Permission::RESERVATIONS]));

        $this->get(route('admin.settings.reservations'))->assertRedirect(route('admin.dashboard'));
        $this->save([$role->id => $this->limits(['max_reservations_day' => 9])])->assertRedirect(route('admin.dashboard'));

        $this->assertSame(2, $role->refresh()->max_reservations_day);
    }
}
