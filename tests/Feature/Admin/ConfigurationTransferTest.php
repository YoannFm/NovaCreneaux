<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Slot;
use App\Services\Configuration\ConfigurationExporter;
use App\Services\Configuration\ConfigurationFile;
use App\Services\Configuration\ConfigurationImporter;
use App\Services\Configuration\ConfigurationTemplates;
use App\Services\SettingService;
use App\Support\Permission;
use App\Support\ReservationLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Parametres > Configuration : export, import et modeles, sans secret ni suppression. */
class ConfigurationTransferTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private function cdi(): Resource
    {
        $resource = Resource::create(['name' => 'CDI', 'description' => 'Lecture', 'is_active' => true, 'open_time' => '17:00', 'close_time' => '11:30', 'closed_days' => [6, 7]]);
        $resource->slots()->create(['name' => '8h - 9h', 'start_time' => '08:00', 'end_time' => '09:00', 'max_capacity' => 24, 'is_active' => true]);

        return $resource;
    }

    private function file(array $content): array
    {
        return ['format' => ConfigurationFile::FORMAT, 'version' => ConfigurationFile::VERSION] + $content;
    }

    private function import(array $content): \App\Services\Configuration\ImportReport
    {
        return app(ConfigurationImporter::class)->import($this->file($content), 'test');
    }

    public function test_the_export_holds_resources_slots_roles_and_texts_but_never_a_secret(): void
    {
        $this->actingAs($this->admin());
        $this->cdi();
        $settings = app(SettingService::class);
        $settings->set('user_term', 'adhérent');
        $settings->set('mail_smtp_password', 'mot-de-passe-smtp');
        $settings->set('trusted_proxies', '10.0.0.1');
        Role::create(['name' => 'delegue', 'label' => 'Délégué', 'color' => '#123456', 'permissions' => [Permission::RESERVATIONS, 'inconnue.voir'], 'max_reservations_day' => 3]);

        $response = $this->post(route('admin.settings.configuration.export'), ['password' => 'password'])->assertOk();
        $json = $response->streamedContent();
        $data = json_decode($json, true);

        $this->assertSame(ConfigurationFile::FORMAT, $data['format']);
        $this->assertSame(['user_term' => 'adhérent'], array_intersect_key($data['settings'], ['user_term' => 1]));
        $this->assertSame('CDI', $data['resources'][0]['name']);
        $this->assertSame(['name' => '8h - 9h', 'start_time' => '08:00', 'end_time' => '09:00', 'max_capacity' => 24, 'is_active' => true], $data['resources'][0]['slots'][0]);
        $delegue = collect($data['roles'])->firstWhere('name', 'delegue');
        $this->assertSame([Permission::RESERVATIONS], $delegue['permissions']);
        $this->assertSame(3, $delegue['max_reservations_day']);
        $this->assertNull(collect($data['roles'])->firstWhere('name', 'admin'), 'les roles administrateurs ne partent pas');
        $this->assertStringNotContainsString('mot-de-passe-smtp', $json);
        $this->assertStringNotContainsString('trusted_proxies', $json);
        $this->assertStringNotContainsString('enc:v1', $json);
        $this->assertTrue(ActivityLog::where('action', 'Configuration exportée')->exists());
    }

    public function test_an_export_imported_on_an_empty_site_gives_the_same_configuration(): void
    {
        $this->actingAs($this->admin());
        $this->cdi();
        Role::defaultUser()->update(['max_reservations_day' => 2, 'max_reservations_week' => ReservationLimits::UNLIMITED]);
        $export = app(ConfigurationExporter::class)->export();

        Slot::query()->delete();
        Resource::query()->delete();
        Role::defaultUser()->update(['max_reservations_day' => 1, 'max_reservations_week' => 4]);
        app(ConfigurationImporter::class)->import($export, 'test');

        $this->assertSame(['CDI'], Resource::pluck('name')->all());
        $this->assertSame([6, 7], Resource::first()->closed_days);
        $this->assertSame(1, Slot::count());
        $this->assertSame(2, Role::defaultUser()->max_reservations_day);
        $this->assertSame(ReservationLimits::UNLIMITED, Role::defaultUser()->max_reservations_week);
        $this->assertSame('aucun changement', app(ConfigurationImporter::class)->import($export, 'test')->summary(), 'un second import ne change rien');
    }

    public function test_import_merges_and_never_deletes_so_no_reservation_is_lost(): void
    {
        $this->actingAs($this->admin());
        $resource = $this->cdi();
        $slot = $resource->slots()->first();
        $other = Resource::create(['name' => 'Salle B', 'is_active' => true, 'open_time' => '17:00', 'close_time' => '11:30', 'closed_days' => []]);
        Reservation::create(['user_id' => $this->student()->id, 'slot_id' => $slot->id, 'reservation_date' => now()->addDay()->toDateString()]);

        $report = $this->import(['resources' => [[
            'name' => 'CDI', 'open_time' => '12:00', 'close_time' => '11:30',
            'slots' => [
                ['name' => 'Première heure', 'start_time' => '08:00', 'end_time' => '09:00', 'max_capacity' => 20],
                ['name' => '9h - 10h', 'start_time' => '09:00', 'end_time' => '10:00', 'max_capacity' => 20],
            ],
        ]]]);

        $this->assertSame('1 ressource mise à jour, 1 créneau créé, 1 créneau mis à jour', $report->summary());
        $this->assertSame('Première heure', $slot->fresh()->name, 'le creneau est retrouve par ses horaires');
        $this->assertSame(20, $slot->fresh()->max_capacity);
        $this->assertSame('Lecture', $resource->fresh()->description, 'un champ absent du fichier est garde');
        $this->assertNotNull($other->fresh());
        $this->assertSame(1, Reservation::count());
    }

    public function test_an_admin_role_is_never_touched_and_an_imported_role_never_becomes_admin(): void
    {
        $this->actingAs($this->admin());

        $report = $this->import(['roles' => [
            ['name' => 'admin', 'label' => 'Pirate', 'permissions' => []],
            ['name' => 'nouveau', 'label' => 'Nouveau', 'is_admin' => true, 'locked' => true, 'permissions' => [Permission::USERS, 'inconnue.voir']],
        ]]);

        $this->assertSame('Administrateur', Role::where('name', 'admin')->value('label'));
        $nouveau = Role::where('name', 'nouveau')->first();
        $this->assertFalse($nouveau->is_admin);
        $this->assertFalse($nouveau->locked);
        $this->assertSame([Permission::USERS], $nouveau->permissions);
        $this->assertSame(Role::defaultUser()->max_reservations_day, $nouveau->max_reservations_day, 'limites du role par defaut');
        $this->assertStringContainsString('rôle administrateur', $report->skipped()[0]);
    }

    public function test_only_whitelisted_settings_are_imported(): void
    {
        $this->actingAs($this->admin());

        $this->import(['settings' => ['user_term' => 'agent', 'theme_accent' => '#112233', 'site_url' => 'https://pirate.exemple.fr', 'mail_smtp_password' => 'x', 'maintenance_mode' => '1']]);

        $this->assertSame('agent', setting('user_term'));
        $this->assertSame('#112233', setting('theme_accent'));
        $this->assertNotSame('https://pirate.exemple.fr', setting('site_url'));
        $this->assertNotSame('x', setting('mail_smtp_password'));
        $this->assertNotSame('1', setting('maintenance_mode'));
        $log = ActivityLog::where('action', 'Configuration importée')->latest('id')->first();
        $this->assertSame('agent', $log->changes['user_term']['new']);
        $this->assertArrayNotHasKey('site_url', $log->changes);
    }

    public function test_an_invalid_file_changes_nothing(): void
    {
        $this->actingAs($this->admin());

        try {
            $this->import(['resources' => [
                ['name' => 'Valide', 'open_time' => '17:00', 'close_time' => '11:30'],
                ['name' => 'Cassée', 'open_time' => '17:00', 'close_time' => '11:30', 'slots' => [['name' => 'x', 'start_time' => '10:00', 'end_time' => '09:00', 'max_capacity' => 5]]],
            ], 'roles' => [['name' => 'user', 'max_reservations_day' => -5]]]);
            $this->fail('ValidationException attendue');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('resources.1.slots.0.end_time', $e->errors());
            $this->assertArrayHasKey('roles.0.max_reservations_day', $e->errors());
        }

        $this->assertSame(0, Resource::count());
    }

    public function test_the_page_imports_a_file_and_reports_errors_of_a_foreign_file(): void
    {
        $this->actingAs($this->admin());
        $page = route('admin.settings.configuration.index');
        $good = UploadedFile::fake()->createWithContent('config.json', json_encode($this->file(['resources' => [['name' => 'Salle C', 'open_time' => '17:00', 'close_time' => '11:30']]])));

        $this->from($page)->post(route('admin.settings.configuration.import'), ['configuration' => $good, 'password' => 'password'])
            ->assertRedirect($page)->assertSessionHas('success', 'Configuration importée : 1 ressource créée.');
        $this->assertTrue(ActivityLog::where('action', 'Configuration importée')->where('context', 'like', 'fichier config.json%')->exists());

        $foreign = UploadedFile::fake()->createWithContent('autre.json', '{"hello": "world"}');
        $this->from($page)->post(route('admin.settings.configuration.import'), ['configuration' => $foreign, 'password' => 'password'])->assertRedirect($page);
        $this->get($page)->assertSee("Rien n'a été importé", false)->assertSee("pas une configuration de NovaCreneaux", false);
    }

    public function test_every_template_is_valid_and_imports_resources_slots_and_limits(): void
    {
        $this->actingAs($this->admin());
        $templates = app(ConfigurationTemplates::class)->all();
        $this->assertSame(['cdi', 'materiel', 'salle-de-travail', 'salle-informatique'], array_keys($templates));

        foreach ($templates as $id => $template) {
            $this->assertNotEmpty($template['title'], $id);
            $this->assertNotEmpty($template['description'], $id);
            ConfigurationFile::validate($template);
        }

        $this->post(route('admin.settings.configuration.template', 'materiel'), ['password' => 'password'])->assertSessionHas('success');
        $this->assertSame(['Appareil photo', 'Ordinateurs portables', 'Tablettes'], Resource::orderBy('name')->pluck('name')->all());
        $this->assertSame(6, Slot::count());
        $this->assertSame(1440, Role::defaultUser()->cancel_deadline_minutes);
        $this->assertSame('Utilisateur', Role::defaultUser()->label, 'un modele ne renomme pas le role');

        $this->post(route('admin.settings.configuration.template', 'inconnu'), ['password' => 'password'])->assertNotFound();
    }

    public function test_every_action_requires_the_administrator_password(): void
    {
        $this->actingAs($this->admin());
        $page = route('admin.settings.configuration.index');
        $file = UploadedFile::fake()->createWithContent('config.json', json_encode($this->file(['resources' => [['name' => 'Salle C', 'open_time' => '17:00', 'close_time' => '11:30']]])));

        $export = $this->from($page)->post(route('admin.settings.configuration.export'), ['password' => 'mauvais']);
        $export->assertRedirect($page)->assertSessionHas('error', 'Mot de passe incorrect : export annulé.');
        $this->assertNull($export->headers->get('Content-Disposition'));
        $this->from($page)->post(route('admin.settings.configuration.import'), ['configuration' => $file])
            ->assertSessionHas('error', 'Mot de passe incorrect : import annulé.');
        $this->from($page)->post(route('admin.settings.configuration.template', 'cdi'), ['password' => 'mauvais'])
            ->assertSessionHas('error', 'Mot de passe incorrect : modèle annulé.');

        $this->assertSame(0, Resource::count());
        $this->assertSame(3, ActivityLog::where('action', 'like', 'Mot de passe refusé pour la configuration%')->count());
        $this->assertFalse(ActivityLog::where('action', 'Configuration exportée')->exists());
    }

    public function test_the_page_lists_the_templates_and_is_reserved_to_administrators(): void
    {
        $this->actingAs($this->staff([Permission::RESOURCES]))->get(route('admin.settings.configuration.index'))->assertRedirect();

        $this->actingAs($this->admin())->get(route('admin.settings.configuration.index'))->assertOk()
            ->assertSee('Télécharger la configuration')->assertSee('Salle informatique')->assertSee('Prêt de matériel')
            ->assertSee('Réservations par jour : 2 par jour');
    }
}
