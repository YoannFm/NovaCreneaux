<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use App\Models\Webhook;
use App\Services\SettingService;
use App\Support\ActivityChanges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Detail des entrees du journal (oeil) : avant / apres des modifications, jamais un secret. */
class ActivityLogDetailTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private function last(string $action): ActivityLog
    {
        return ActivityLog::where('action', $action)->latest('id')->firstOrFail();
    }

    public function test_a_modified_record_keeps_only_the_changed_fields_with_before_and_after(): void
    {
        $this->actingAs($this->admin());
        $resource = Resource::create(['name' => 'CDI', 'is_active' => true, 'open_time' => '12:00', 'close_time' => '11:30', 'closed_days' => []]);

        $resource->update(['close_time' => '11:45', 'closed_days' => [6, 7], 'name' => 'CDI']);

        // Heures au format de la base (HH:MM:SS, App\Casts\TimeOfDay). assertEquals et non assertSame : une colonne JSON
        // de MySQL 8 range les cles a sa facon ("new" avant "old"), seul leur contenu compte.
        $this->assertEquals([
            'close_time' => ['old' => '11:30:00', 'new' => '11:45:00'],
            'closed_days' => ['old' => [], 'new' => [6, 7]],
        ], $this->last('Ressource modifiée')->changes);
        $this->assertSame(['new' => 'CDI'], $this->last('Ressource créée')->changes['name']);
    }

    public function test_a_deleted_record_keeps_its_last_values(): void
    {
        $this->actingAs($this->admin());
        $resource = Resource::create(['name' => 'Salle B', 'is_active' => true, 'open_time' => '12:00', 'close_time' => '11:30', 'closed_days' => []]);

        $resource->delete();

        $this->assertEquals(['old' => 'Salle B'], $this->last('Ressource supprimée')->changes['name']);
    }

    public function test_a_secret_is_never_written_only_marked_as_changed(): void
    {
        $this->actingAs($this->admin());
        $hook = Webhook::create(['name' => 'Discord', 'url' => 'https://hooks.example.com/x', 'secret' => 'secret-1', 'events' => ['ping'], 'format' => 'json', 'is_active' => true]);
        $hook->update(['secret' => 'secret-2']);

        $created = $this->last('Webhook créé')->changes;
        $this->assertArrayNotHasKey('secret', $created);
        $this->assertEquals(['old' => ActivityChanges::MASKED, 'new' => ActivityChanges::MASKED], $this->last('Webhook modifié')->changes['secret']);
        $this->assertStringNotContainsString('secret-', json_encode(ActivityLog::pluck('changes')));
    }

    public function test_editing_an_account_shows_what_changed_including_the_role_but_not_the_password(): void
    {
        $this->actingAs($this->admin());
        $student = $this->student(['name' => 'Léa Martin', 'classe' => '2nde A']);
        $staffRole = Role::create(['name' => 'delegue', 'label' => 'Délégué', 'color' => '#123456']);

        $this->put(route('admin.users.update', $student), [
            'name' => 'Léa Martin', 'email' => $student->email, 'classe' => '1ère G', 'role_id' => $staffRole->id,
            'password' => 'Nouveau-Mot-2026', 'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $changes = $this->last('Utilisateur modifié')->changes;
        $this->assertEquals(['old' => '2nde A', 'new' => '1ère G'], $changes['classe']);
        $this->assertEquals(['old' => 'Utilisateur', 'new' => 'Délégué'], $changes['role']);
        $this->assertEquals(['old' => ActivityChanges::MASKED, 'new' => ActivityChanges::MASKED], $changes['password']);
        $this->assertArrayNotHasKey('name', $changes);
        $this->assertStringNotContainsString('Nouveau-Mot', json_encode($changes));
    }

    public function test_settings_changes_list_each_setting_and_mask_secrets(): void
    {
        $this->actingAs($this->admin());
        app(SettingService::class)->set('mail_smtp_password', 'ancien-secret');

        $this->post(route('admin.settings.mail.update'), [
            'mail_driver' => 'smtp', 'mail_smtp_host' => 'smtp.exemple.fr', 'mail_smtp_password' => 'nouveau-secret',
        ])->assertSessionHasNoErrors();

        $changes = $this->last('Paramètres modifiés')->changes;
        $this->assertSame('smtp.exemple.fr', $changes['mail_smtp_host']['new']);
        $this->assertEquals(['old' => ActivityChanges::MASKED, 'new' => ActivityChanges::MASKED], $changes['mail_smtp_password']);
        $this->assertStringNotContainsString('secret', json_encode(ActivityLog::pluck('changes')));
    }

    public function test_a_login_is_written_once(): void
    {
        // Le listener etait declare a la main ET decouvert par Laravel : chaque connexion etait inscrite deux fois
        $user = User::factory()->create(['password' => bcrypt('Mot-De-Passe-2026'), 'role_id' => Role::defaultUser()->id]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'Mot-De-Passe-2026'])->assertRedirect();

        $this->assertSame(1, ActivityLog::where('action', 'Connexion')->where('user_id', $user->id)->count());
    }

    public function test_the_log_page_has_an_eye_with_the_full_detail_of_each_entry(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $resource = Resource::create(['name' => 'CDI', 'is_active' => true, 'open_time' => '12:00', 'close_time' => '11:30', 'closed_days' => []]);
        $resource->update(['close_time' => '11:45']);
        ActivityLog::create(['user_id' => $admin->id, 'user_name' => 'Léa Martin', 'action' => 'Connexion', 'ip_address' => '203.0.113.7', 'created_at' => '2026-11-25 08:42:10']);

        $page = $this->get(route('admin.logs.index'))->assertOk();

        $page->assertSee('js-log-detail', false)->assertSee('id="log-detail-modal"', false)
            ->assertSee('Fermeture (jour même)')->assertSee('11:30')->assertSee('11:45')
            ->assertSee("s'est connecté(e)")->assertSee('le 25/11/2026 à 08:42:10')->assertSee('203.0.113.7');
    }
}
