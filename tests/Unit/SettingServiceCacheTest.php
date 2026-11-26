<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le cache des reglages garde ce qui est en base, jamais la valeur par defaut d'un appel. */
class SettingServiceCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_missing_setting_gives_each_caller_its_own_default(): void
    {
        $settings = app(SettingService::class);

        $this->assertSame('', $settings->get('timezone', ''));
        $this->assertSame('Europe/Paris', $settings->get('timezone', 'Europe/Paris'), "le premier defaut n'est pas reste en cache");
    }

    public function test_set_missing_only_writes_absent_settings_and_refreshes_the_cache(): void
    {
        $settings = app(SettingService::class);
        $settings->set('app_name', 'Médiathèque');
        $settings->get('registration_open', 'pas encore');
        $before = Setting::count();

        $settings->setMissing(['app_name' => 'NovaCreneaux', 'registration_open' => '0']);

        $this->assertSame('Médiathèque', $settings->get('app_name'));
        $this->assertSame('0', $settings->get('registration_open', 'pas encore'));
        $this->assertSame($before + 1, Setting::count(), 'seul le reglage absent est ajoute');
    }
}
