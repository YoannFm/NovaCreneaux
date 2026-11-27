<?php

namespace Tests\Feature\Admin;

use App\Mail\ReservationCancelled;
use App\Models\ActivityLog;
use App\Models\NavItem;
use App\Models\Page;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use App\Models\UserNotification;
use App\Providers\AppServiceProvider;
use App\Services\SettingService;
use App\Support\Permission;
use App\Support\ThemeSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/**
 * Coherence de l'administration : reglages qui ne peuvent pas casser le site, roles et pages restreintes,
 * historique du planning, limites du personnel delegue, formulaires a cases, journal de la navigation et du theme.
 */
class AdminConsistencyTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    public function test_an_unknown_timezone_is_refused_and_never_breaks_the_site(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.settings.general.update'), ['app_name' => 'Site', 'timezone' => 'Europe/Pariss'])->assertSessionHasErrors('timezone');
        $this->post(route('admin.settings.general.update'), ['app_name' => 'Site', 'timezone' => 'America/Toronto'])->assertSessionHasNoErrors();
        $this->assertSame('America/Toronto', setting('timezone'));

        // Valeur deja en base (ecrite a la main) : ignoree, les reglages suivants s'appliquent, le site repond
        app(SettingService::class)->set('timezone', 'Europe/Pariss');
        app(SettingService::class)->set('site_url', 'https://reservation.exemple.fr');
        (new AppServiceProvider($this->app))->boot();
        $this->assertSame('https://reservation.exemple.fr', config('app.url'));
        $this->get(route('admin.dashboard'))->assertOk();
        auth()->logout();
        $this->get(route('login'))->assertOk();
    }

    public function test_an_administrator_role_keeps_its_status_when_the_other_administrators_are_disabled(): void
    {
        $direction = Role::create(['name' => 'direction', 'label' => 'Direction', 'color' => '#123456', 'is_admin' => true, 'locked' => false]);
        $me = User::factory()->create(['role_id' => $direction->id, 'is_admin' => true]);
        $this->admin()->update(['is_active' => false]);

        $this->actingAs($me)->put(route('admin.roles.update', $direction), ['label' => 'Direction', 'color' => '#123456', 'is_admin' => '0'])
            ->assertSessionHas('error');
        $this->assertTrue($direction->fresh()->is_admin);

        // Un autre administrateur actif : le role peut perdre son statut
        $this->admin();
        $this->put(route('admin.roles.update', $direction), ['label' => 'Direction', 'color' => '#123456', 'is_admin' => '0']);
        $this->assertFalse($direction->fresh()->is_admin);
    }

    public function test_a_role_giving_access_to_a_restricted_page_cannot_be_deleted(): void
    {
        $prof = Role::create(['name' => 'prof', 'label' => 'Prof', 'color' => '#123456']);
        $page = Page::create(['title' => 'Interne', 'slug' => 'interne', 'content' => '<p>Secret</p>', 'is_enabled' => true, 'is_restricted' => true]);
        $page->roles()->sync([$prof->id]);

        $this->actingAs($this->admin())->delete(route('admin.roles.destroy', $prof))->assertSessionHas('error');

        $this->assertNotNull($prof->fresh());
        $this->actingAs($this->student())->get('/p/interne')->assertForbidden();

        $page->roles()->detach();
        $this->actingAs($this->admin())->delete(route('admin.roles.destroy', $prof))->assertRedirect(route('admin.roles.index'));
        $this->assertNull($prof->fresh());
    }

    private function slot()
    {
        return Resource::create(['name' => 'CDI', 'is_active' => true, 'open_time' => '00:00', 'close_time' => '23:59', 'closed_days' => []])
            ->slots()->create(['name' => 'M', 'start_time' => '08:00', 'end_time' => '09:00', 'max_capacity' => 5, 'is_active' => true]);
    }

    public function test_a_finished_session_stays_in_the_history(): void
    {
        Mail::fake();
        $slot = $this->slot();
        $student = $this->student();
        $day = now()->subDays(3)->toDateString();
        $past = Reservation::create(['user_id' => $student->id, 'slot_id' => $slot->id, 'reservation_date' => $day]);
        $this->actingAs($this->admin());

        $this->get(route('admin.reservations.index', ['date' => $day]))->assertOk()->assertDontSee(route('admin.reservations.destroy', $past), false);
        $this->delete(route('admin.reservations.destroy', $past))->assertSessionHas('error');

        $this->assertNotNull($past->fresh());
        Mail::assertNothingSent();
        $this->assertSame(0, UserNotification::where('user_id', $student->id)->count());
    }

    public function test_staff_does_not_see_the_delete_button_of_an_administrator_reservation(): void
    {
        $slot = $this->slot();
        $date = now()->addDay()->toDateString();
        $ofAdmin = Reservation::create(['user_id' => $this->admin()->id, 'slot_id' => $slot->id, 'reservation_date' => $date]);
        $ofStudent = Reservation::create(['user_id' => $this->student()->id, 'slot_id' => $slot->id, 'reservation_date' => $date]);

        $html = $this->actingAs($this->staff([Permission::RESERVATIONS]))->get(route('admin.reservations.index', ['date' => $date]))->getContent();

        $this->assertStringNotContainsString(route('admin.reservations.destroy', $ofAdmin), $html);
        $this->assertStringContainsString(route('admin.reservations.destroy', $ofStudent), $html);
    }

    public function test_staff_notifications_stay_within_the_accounts_they_manage(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $own = UserNotification::create(['user_id' => $admin->id, 'title' => 'Privée admin', 'type' => 'info']);
        $this->actingAs($this->staff([Permission::NOTIFICATIONS]));

        $this->get(route('admin.notifications.create'))->assertOk()->assertDontSee($admin->email)->assertSee($student->email);
        $this->get(route('admin.notifications.index'))->assertOk()->assertDontSee('Privée admin');
        $this->delete(route('admin.notifications.destroy', $own))->assertForbidden();

        $this->post(route('admin.notifications.store'), ['title' => 'Info', 'level' => 'info', 'target' => 'users', 'user_ids' => [$admin->id, $student->id]])
            ->assertSessionHas('success', 'Notification envoyée à 1 utilisateur(s).');
        $this->post(route('admin.notifications.store'), ['title' => 'Tous', 'level' => 'info', 'target' => 'all']);
        $this->assertSame(0, UserNotification::where('user_id', $admin->id)->where('title', '!=', 'Privée admin')->count());
        $this->assertSame(2, UserNotification::where('user_id', $student->id)->count());

        // Liste des comptes : le personnel delegue ne voit pas les administrateurs
        $this->actingAs($this->staff([Permission::USERS], 'secretariat'))->get(route('admin.users.index'))->assertOk()
            ->assertDontSee($admin->email)->assertSee($student->email);
    }

    public function test_unchecked_boxes_are_saved_as_unchecked(): void
    {
        $this->actingAs($this->admin());
        $link = ['label' => 'Lien', 'type' => 'external', 'url' => 'https://exemple.fr', 'target' => '_self'];

        $this->post(route('admin.nav.store'), $link + ['is_active' => '0', 'admin_only' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(1, substr_count($this->get(route('admin.nav.index'))->getContent(), 'Élément de navigation ajouté.'), 'message affiche une seule fois');
        $item = NavItem::where('label', 'Lien')->firstOrFail();
        $this->assertFalse($item->is_active);
        $this->assertTrue($item->admin_only);
        $this->get(route('admin.nav.edit', $item))->assertSee('name="admin_only"', false);

        $this->post(route('admin.users.store'), ['name' => 'Inactif', 'email' => 'inactif@exemple.fr', 'role_id' => Role::defaultUser()->id, 'is_active' => '0']);
        $this->assertFalse(User::where('email', 'inactif@exemple.fr')->firstOrFail()->is_active);
    }

    public function test_navigation_and_theme_changes_are_logged(): void
    {
        $this->actingAs($this->staff([Permission::NAVIGATION, Permission::APPEARANCE]));

        $this->post(route('admin.nav.store'), ['label' => 'Externe', 'type' => 'external', 'url' => 'https://exemple.fr', 'target' => '_self', 'is_active' => '1']);
        $this->assertTrue(ActivityLog::where('action', 'Lien de navigation créé')->where('context', 'Externe')->exists());

        $this->put(route('admin.themes.update'), array_map(fn () => '#123456', ThemeSettings::rules()))->assertSessionHasNoErrors();
        $log = ActivityLog::where('action', 'Thème modifié')->firstOrFail();
        $this->assertSame('#123456', $log->changes[ThemeSettings::ACCENT]['new']);
    }

    public function test_a_page_description_fits_its_column(): void
    {
        $this->actingAs($this->admin())->post(route('admin.pages.store'), ['title' => 'P', 'slug' => 'p', 'content' => 'x', 'description' => str_repeat('a', 256)])
            ->assertSessionHasErrors('description');
    }

    public function test_marking_a_notification_as_read_twice_is_harmless(): void
    {
        $student = $this->student();
        $notification = UserNotification::create(['user_id' => $student->id, 'title' => 'x', 'type' => 'info']);
        $this->actingAs($student);

        $this->post(route('notifications.read', $notification->id))->assertRedirect();
        $this->post(route('notifications.read', $notification->id))->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_staff_lists_and_exports_stay_within_the_accounts_they_manage(): void
    {
        $admin = $this->admin();
        $student = $this->student(['email' => 'eleve@exemple.fr']);
        \App\Models\Sanction::create(['user_id' => $admin->id, 'admin_id' => $admin->id, 'type' => 'warning', 'reason' => 'Motif interne admin']);
        \App\Models\Sanction::create(['user_id' => $student->id, 'admin_id' => $admin->id, 'type' => 'warning', 'reason' => 'Retard']);

        $this->actingAs($this->staff([Permission::USERS, Permission::SANCTIONS]));
        $zip = new \ZipArchive();
        $zip->open($this->get(route('admin.users.export-zip'))->assertOk()->baseResponse->getFile()->getPathname());
        $csv = $zip->getFromName('utilisateurs.csv');
        $zip->close();
        $this->assertStringContainsString('eleve@exemple.fr', $csv);
        $this->assertStringNotContainsString($admin->email, $csv);

        $this->get(route('admin.sanctions.index'))->assertOk()->assertSee('Retard')->assertDontSee('Motif interne admin');
    }

    public function test_the_dashboard_shows_only_what_the_staff_member_may_see(): void
    {
        $slot = $this->slot();
        Reservation::create(['user_id' => $this->student(['name' => 'Zoé Élève'])->id, 'slot_id' => $slot->id, 'reservation_date' => now()->addDay()->toDateString()]);

        $this->actingAs($this->staff([Permission::PAGES]))->get(route('admin.dashboard'))->assertOk()
            ->assertDontSee('Zoé Élève')->assertDontSee('Total réservations')->assertDontSee('Utilisateurs</h5>', false);
        $this->actingAs($this->staff([Permission::RESERVATIONS], 'accueil'))->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Zoé Élève')->assertSee('Total réservations');
    }

    public function test_an_import_giving_administration_access_to_a_role_with_accounts_warns(): void
    {
        $this->student();
        $file = json_encode(['format' => 'novacreneaux-configuration', 'version' => 1, 'roles' => [['name' => 'user', 'permissions' => [Permission::USERS]]]]);

        $this->actingAs($this->admin())->post(route('admin.settings.configuration.import'), [
            'configuration' => \Illuminate\Http\UploadedFile::fake()->createWithContent('conf.json', $file), 'password' => 'password',
        ])->assertSessionHas('warning', fn (string $warning) => str_contains($warning, "donne maintenant accès à l'administration à 1 compte(s)"));

        $this->assertStringContainsString('attention', ActivityLog::where('action', 'Configuration importée')->value('context'));
    }

    public function test_deactivating_or_closing_warns_about_kept_upcoming_reservations(): void
    {
        $slot = $this->slot();
        $resource = $slot->resource;
        $day = now()->addDays(2);
        Reservation::create(['user_id' => $this->student()->id, 'slot_id' => $slot->id, 'reservation_date' => $day->toDateString()]);
        $this->actingAs($this->admin());
        $fields = ['name' => 'CDI', 'open_time' => '00:00', 'close_time' => '23:59'];

        $this->put(route('admin.resources.update', $resource), $fields + ['is_active' => '1', 'closed_days' => [$day->isoWeekday()]])
            ->assertSessionHas('warning', fn (string $w) => str_starts_with($w, '1 réservation(s) à venir'));
        $this->put(route('admin.resources.update', $resource), $fields + ['is_active' => '1', 'closed_days' => [$day->isoWeekday()]])->assertSessionMissing('warning');

        $this->put(route('admin.slots.update', [$resource, $slot]), ['name' => 'M', 'start_time' => '08:00', 'end_time' => '09:00', 'max_capacity' => 5])
            ->assertSessionHas('warning', fn (string $w) => str_starts_with($w, 'Créneau désactivé : 1 réservation(s)'));
        $this->assertSame(1, Reservation::count(), 'rien n\'est annule');
    }
}
