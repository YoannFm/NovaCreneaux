<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Sanction;
use App\Models\User;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class UserSuspensionTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private function suspend(User $target, array $fields = [])
    {
        return $this->from(route('admin.users.index'))->post(route('admin.sanctions.store', $target), $fields + [
            'type' => 'ban', 'suspend_user_id' => $target->id, 'reason' => 'Bruit au CDI',
            'severity' => 'moderate', 'permanent' => '0', 'banned_until' => now()->addDays(7)->toDateString(),
        ]);
    }

    public function test_a_temporary_suspension_is_saved_with_its_severity_and_blocks_reservations(): void
    {
        $student = $this->student();
        $this->actingAs($this->admin());

        $this->suspend($student, ['severity' => 'severe'])->assertSessionHasNoErrors()->assertSessionHas('success');

        $ban = $student->activeBan();
        $this->assertNotNull($ban);
        $this->assertSame('severe', $ban->severity);
        $this->assertSame(now()->addDays(7)->toDateString(), $ban->banned_until->toDateString());
        $this->assertTrue(ActivityLog::where('action', 'Utilisateur suspendu')->where('context', 'like', '%gravité Grave')->exists());
    }

    public function test_a_permanent_suspension_ignores_the_end_date(): void
    {
        $student = $this->student();
        $this->actingAs($this->admin());

        // Champ de date grise par la fenetre : il n'est pas envoye
        $this->suspend($student, ['permanent' => '1', 'banned_until' => null])->assertSessionHasNoErrors();

        $this->assertTrue($student->activeBan()->isPermanent());
    }

    public function test_missing_or_invalid_fields_are_refused_in_the_suspend_error_bag(): void
    {
        $student = $this->student();
        $this->actingAs($this->admin());

        $this->suspend($student, ['reason' => '', 'severity' => 'enorme', 'banned_until' => now()->toDateString()])
            ->assertSessionHasErrorsIn('suspend', ['reason', 'severity', 'banned_until']);
        $this->suspend($student, ['banned_until' => null])
            ->assertSessionHasErrorsIn('suspend', ['banned_until' => 'Indiquez la date de fin, ou choisissez « Définitive ».']);

        $this->assertNull($student->activeBan());
    }

    public function test_a_refused_form_reopens_the_window_on_the_same_account_with_the_typed_reason(): void
    {
        $student = $this->student(['name' => 'Eleve Fenetre']);
        $this->actingAs($this->admin());

        $this->suspend($student, ['reason' => 'Raison saisie', 'severity' => '']);

        $this->get(route('admin.users.index'))->assertOk()
            ->assertSee('data-reopen="1"', false)
            ->assertSee('action="' . route('admin.sanctions.store', $student) . '"', false)
            ->assertSee('Raison saisie');
    }

    public function test_an_account_already_suspended_cannot_be_suspended_twice(): void
    {
        $student = $this->student();
        $this->actingAs($this->admin());
        $this->suspend($student);

        $this->suspend($student)->assertSessionHas('error');

        $this->assertSame(1, Sanction::where('user_id', $student->id)->count());
    }

    public function test_nobody_can_suspend_themself(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->suspend($admin)->assertForbidden();
    }

    public function test_the_list_marks_suspended_accounts_and_offers_the_button_for_the_others(): void
    {
        $suspended = $this->student(['name' => 'Eleve Suspendu']);
        $other = $this->student(['name' => 'Eleve Libre']);
        $admin = $this->admin();
        $this->actingAs($admin)->suspend($suspended);

        $page = $this->get(route('admin.users.index'))->assertOk();

        $page->assertSee('title="Suspendu"', false)
            ->assertSee('data-action="' . route('admin.sanctions.store', $other) . '"', false)
            ->assertDontSee('data-action="' . route('admin.sanctions.store', $suspended) . '"', false)
            ->assertDontSee('data-action="' . route('admin.sanctions.store', $admin) . '"', false);
    }

    public function test_the_profile_shows_the_suspension_and_it_can_be_lifted(): void
    {
        $student = $this->student();
        $this->actingAs($this->admin());
        $this->suspend($student, ['severity' => 'minor', 'reason' => 'Retards répétés']);
        $ban = $student->activeBan();

        $this->get(route('admin.users.edit', $student))->assertOk()
            ->assertSee("Suspendu jusqu'au " . $ban->banned_until->format('d/m/Y'))
            ->assertSee('Mineure')->assertSee('Retards répétés')->assertSee('Lever la suspension');

        $this->delete(route('admin.sanctions.destroy', $ban))->assertSessionHas('success', 'Suspension levée.');
        $this->assertNull($student->activeBan());
        $this->get(route('admin.users.edit', $student))->assertSee('data-bs-target="#suspend-modal"', false);
    }

    public function test_staff_without_the_sanctions_permission_see_no_button_and_cannot_suspend(): void
    {
        $student = $this->student();
        $this->actingAs($this->staff([Permission::USERS]));

        $this->get(route('admin.users.index'))->assertOk()->assertDontSee('suspend-modal');
        $this->suspend($student)->assertRedirect(route('admin.dashboard'));
        $this->assertNull($student->activeBan());
    }

    public function test_the_last_day_of_a_suspension_still_counts_as_active(): void
    {
        $student = $this->student();
        $ban = Sanction::create(['user_id' => $student->id, 'admin_id' => $this->admin()->id, 'type' => 'ban', 'reason' => 'x', 'banned_until' => now()->toDateString()]);

        $this->assertTrue($ban->fresh()->isActive());
        $this->assertNotNull($student->activeBan());
    }
}
