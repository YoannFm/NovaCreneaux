<?php

namespace Tests\Feature;

use App\Mail\EmailChanged;
use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use App\Support\Permission;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Droits d'administrateur suivis du role, delegation appliquee partout, secret TOTP chiffre, adresse protegee. */
class AccountSecurityTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private function adminRole(string $name): Role
    {
        return Role::create(['name' => $name, 'label' => ucfirst($name), 'color' => '#123456', 'is_admin' => true]);
    }

    public function test_removing_admin_rights_from_a_role_removes_them_from_its_accounts(): void
    {
        $this->actingAs($this->admin());
        $chef = $this->adminRole('chef');
        $this->post(route('admin.users.store'), ['name' => 'Chef', 'email' => 'chef@exemple.fr', 'role_id' => $chef->id])->assertSessionHasNoErrors();
        $member = User::where('email', 'chef@exemple.fr')->firstOrFail();
        $this->assertTrue($member->isAdmin());

        $this->put(route('admin.roles.update', $chef), ['label' => 'Chef', 'color' => '#123456'])->assertSessionHasNoErrors();

        $member->refresh();
        $this->assertFalse($member->isAdmin());
        $this->assertFalse($member->is_admin, 'la copie en base suit le role');
        $this->actingAs($member)->get(route('admin.settings.general'))->assertRedirect();
    }

    public function test_the_last_admin_role_in_use_cannot_lose_its_rights(): void
    {
        DB::table('users')->update(['role_id' => null]);
        $chef = $this->adminRole('chef');
        $only = User::factory()->create(['role_id' => $chef->id, 'is_admin' => true]);

        $this->actingAs($only)->put(route('admin.roles.update', $chef), ['label' => 'Chef', 'color' => '#123456'])
            ->assertSessionHas('error');

        $this->assertTrue($chef->fresh()->is_admin);
    }

    public function test_the_role_decides_even_when_the_copied_flag_disagrees(): void
    {
        $stale = User::factory()->create(['role_id' => Role::defaultUser()->id]);
        DB::table('users')->where('id', $stale->id)->update(['is_admin' => true]);
        $admin = $this->admin();
        DB::table('users')->where('id', $admin->id)->update(['is_admin' => false]);

        $this->assertFalse($stale->fresh()->isAdmin());
        $this->assertTrue($admin->fresh()->isAdmin());
        // Suppression de son propre compte : refusee a un administrateur selon son role, plus selon la seule colonne
        $this->actingAs($admin->fresh())->delete('/profile', ['password' => 'password'])->assertSessionHas('error');
        $this->assertNotNull($admin->fresh());
    }

    public function test_the_migration_aligns_the_copied_flag_with_the_role(): void
    {
        $stale = User::factory()->create(['role_id' => Role::defaultUser()->id]);
        DB::table('users')->where('id', $stale->id)->update(['is_admin' => true]);

        (require database_path('migrations/2026_11_26_000002_sync_user_admin_flag_with_role.php'))->up();

        $this->assertSame(0, (int) DB::table('users')->where('id', $stale->id)->value('is_admin'));
    }

    public function test_staff_cannot_delete_the_reservation_of_an_administrator(): void
    {
        $slot = Resource::create(['name' => 'CDI', 'is_active' => true, 'open_time' => '17:00', 'close_time' => '11:30', 'closed_days' => []])
            ->slots()->create(['name' => 'M1', 'start_time' => '08:00', 'end_time' => '09:00', 'max_capacity' => 5, 'is_active' => true]);
        $adminBooking = Reservation::create(['user_id' => $this->admin()->id, 'slot_id' => $slot->id, 'reservation_date' => now()->addDay()->toDateString()]);
        $studentBooking = Reservation::create(['user_id' => $this->student()->id, 'slot_id' => $slot->id, 'reservation_date' => now()->addDay()->toDateString()]);
        $this->actingAs($this->staff([Permission::RESERVATIONS]));

        $this->delete(route('admin.reservations.destroy', $adminBooking))->assertForbidden();
        $this->delete(route('admin.reservations.destroy', $studentBooking))->assertSessionHas('success');

        $this->assertNotNull($adminBooking->fresh());
        $this->assertNull($studentBooking->fresh());
    }

    private function editForm(User $user, array $fields): array
    {
        return $fields + ['name' => $user->name, 'email' => $user->email, 'role_id' => $user->role_id];
    }

    public function test_an_administrator_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->admin();
        $this->admin();
        $this->actingAs($admin);

        $this->patch(route('admin.users.toggle', $admin))->assertSessionHas('error', 'Vous ne pouvez pas désactiver votre propre compte.');

        $this->assertTrue($admin->fresh()->is_active);
        $this->get(route('admin.users.index'))->assertOk();
    }

    public function test_the_last_active_administrator_cannot_lose_the_admin_role(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->put(route('admin.users.update', $admin), $this->editForm($admin, ['role_id' => Role::defaultUser()->id]))->assertSessionHasErrors('role_id');
        $this->assertTrue($admin->fresh()->isAdmin());

        // Avec un autre administrateur actif, le changement est possible
        $this->admin();
        $this->put(route('admin.users.update', $admin), $this->editForm($admin, ['role_id' => Role::defaultUser()->id]))->assertSessionHasNoErrors();
        $this->assertFalse($admin->fresh()->isAdmin());
    }

    public function test_an_inactive_administrator_does_not_count_as_another_one(): void
    {
        $admin = $this->admin();
        $this->admin()->update(['is_active' => false]);

        $this->actingAs($admin)->put(route('admin.users.update', $admin), $this->editForm($admin, ['role_id' => Role::defaultUser()->id]))->assertSessionHasErrors('role_id');

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_the_authenticator_secret_is_stored_encrypted(): void
    {
        $user = User::factory()->create(['two_factor_secret' => 'JBSWY3DPEHPK3PXP']);

        $stored = DB::table('users')->where('id', $user->id)->value('two_factor_secret');
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $stored);
        $this->assertSame('JBSWY3DPEHPK3PXP', $user->fresh()->two_factor_secret);
    }

    public function test_a_secret_unreadable_after_an_app_key_change_breaks_no_page_and_still_asks_for_the_second_factor(): void
    {
        $user = $this->student(['email' => 'lea@exemple.fr', 'two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);
        $otherKey = new Encrypter(random_bytes(32), 'aes-256-cbc');
        DB::table('users')->where('id', $user->id)->update(['two_factor_secret' => $otherKey->encryptString('JBSWY3DPEHPK3PXP')]);

        $this->assertNull($user->fresh()->two_factor_secret);
        $this->assertTrue($user->fresh()->hasTotp(), 'toujours activee : la connexion ne saute pas le second facteur');

        $this->post('/login', ['email' => 'lea@exemple.fr', 'password' => 'password'])->assertRedirect(route('mfa.challenge.form'));
        $this->assertGuest();
        $this->get(route('mfa.challenge.form'))->assertOk();
        $this->post(route('mfa.challenge'), ['method' => 'totp', 'code' => '123456'])->assertSessionHasErrors();
        $this->assertGuest();

        $this->actingAs($user->fresh())->get(route('profile.edit'))->assertOk();
        $this->actingAs($this->admin())->get(route('admin.users.edit', $user))->assertOk();
        $this->get(route('admin.users.index'))->assertOk();
    }

    public function test_the_migration_encrypts_existing_secrets_once(): void
    {
        $plain = User::factory()->create();
        DB::table('users')->where('id', $plain->id)->update(['two_factor_secret' => 'JBSWY3DPEHPK3PXP']);
        $already = User::factory()->create(['two_factor_secret' => 'KRSXG5CTMVRXEZLU']);
        $migration = require database_path('migrations/2026_11_26_000003_encrypt_two_factor_secrets.php');

        $migration->up();
        $migration->up();

        $this->assertSame('JBSWY3DPEHPK3PXP', $plain->fresh()->two_factor_secret);
        $this->assertSame('KRSXG5CTMVRXEZLU', $already->fresh()->two_factor_secret, 'deja chiffre : pas chiffre deux fois');
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', DB::table('users')->where('id', $plain->id)->value('two_factor_secret'));
    }

    public function test_changing_the_email_needs_the_password_warns_the_old_address_and_is_logged(): void
    {
        Mail::fake();
        $user = $this->student(['email' => 'lea@exemple.fr', 'name' => 'Léa']);
        $this->actingAs($user);

        $this->patch('/profile', ['name' => 'Léa', 'email' => 'pirate@exemple.fr'])->assertSessionHasErrors('current_password');
        $this->patch('/profile', ['name' => 'Léa', 'email' => 'pirate@exemple.fr', 'current_password' => 'mauvais'])->assertSessionHasErrors('current_password');
        $this->assertSame('lea@exemple.fr', $user->fresh()->email);
        Mail::assertNothingSent();

        $this->patch('/profile', ['name' => 'Léa', 'email' => 'lea.martin@exemple.fr', 'current_password' => 'password'])->assertSessionHasNoErrors();

        $this->assertSame('lea.martin@exemple.fr', $user->fresh()->email);
        Mail::assertSent(EmailChanged::class, fn (EmailChanged $mail) => $mail->hasTo('lea@exemple.fr') && $mail->newEmail === 'lea.martin@exemple.fr');
        $log = ActivityLog::where('action', 'Profil mis à jour')->latest('id')->firstOrFail();
        $this->assertSame(['old' => 'lea@exemple.fr', 'new' => 'lea.martin@exemple.fr'], $log->changes['email']);
    }

    public function test_other_profile_changes_do_not_ask_for_the_password(): void
    {
        $user = $this->student(['email' => 'lea@exemple.fr']);

        $this->actingAs($user)->patch('/profile', ['name' => 'Léa Martin', 'email' => 'lea@exemple.fr', 'classe' => '1ère G'])->assertSessionHasNoErrors();

        $this->assertSame('1ère G', $user->fresh()->classe);
    }
}
