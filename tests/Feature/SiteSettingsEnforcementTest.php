<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingsEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function setting(string $key, string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        cache()->forget("setting_{$key}");
    }

    private function admin(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role_id' => Role::where('name', 'admin')->value('id'), 'is_admin' => true]);
    }

    private function student(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role_id' => Role::defaultUser()->id]);
    }

    public function test_a_deactivated_user_is_logged_out_on_the_next_request(): void
    {
        $user = $this->student();
        $this->actingAs($user)->get(route('reservations.index'))->assertOk();

        $user->update(['is_active' => false]);

        $this->get(route('reservations.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_maintenance_blocks_students_but_not_admins(): void
    {
        $this->setting('maintenance_mode', '1');
        $this->setting('maintenance_message', '<p>Retour <b>demain</b></p>');

        $this->actingAs($this->student())->get(route('reservations.index'))
            ->assertStatus(503)->assertSee('Retour demain', false);
        $this->actingAs($this->admin())->get(route('reservations.index'))->assertOk();
    }

    public function test_maintenance_keeps_the_login_page_open(): void
    {
        $this->setting('maintenance_mode', '1');

        $this->get('/login')->assertOk();
    }

    public function test_admin_without_mfa_is_sent_to_the_profile_when_2fa_is_required(): void
    {
        $this->setting('require_2fa_admin', '1');
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('profile.edit'));
        $this->actingAs($admin)->get(route('profile.edit'))->assertOk();
    }

    public function test_admin_with_mfa_is_not_redirected_when_2fa_is_required(): void
    {
        $this->setting('require_2fa_admin', '1');
        $admin = $this->admin(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_unverified_students_must_verify_their_email_when_enforced(): void
    {
        $this->setting('force_email_verification', '1');
        $student = $this->student(['email_verified_at' => null]);

        $this->actingAs($student)->get(route('reservations.index'))->assertRedirect(route('verification.notice'));
        $this->actingAs($student)->get(route('verification.notice'))->assertOk();
    }

    public function test_settings_do_nothing_when_disabled(): void
    {
        $student = $this->student(['email_verified_at' => null]);

        $this->actingAs($student)->get(route('reservations.index'))->assertOk();
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk();
    }
}
