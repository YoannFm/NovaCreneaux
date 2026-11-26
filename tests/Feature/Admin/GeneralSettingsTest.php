<?php

namespace Tests\Feature\Admin;

use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Parametres > General : seulement des reglages qui ont un effet. */
class GeneralSettingsTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private function save(array $values)
    {
        return $this->post(route('admin.settings.general.update'), $values + ['app_name' => 'NovaCreneaux']);
    }

    public function test_the_description_is_saved_as_plain_text_and_used_as_meta_description(): void
    {
        $this->actingAs($this->admin());

        $this->save(['site_description' => "<p>Réservez le <strong>CDI</strong> &amp; la salle\n informatique.</p>"])->assertSessionHasNoErrors();

        $this->assertSame('Réservez le CDI & la salle informatique.', setting('site_description'));
        auth()->logout();
        $this->get(route('login'))->assertSee('<meta name="description" content="Réservez le CDI &amp; la salle informatique.">', false);
    }

    public function test_without_description_no_meta_tag_is_written(): void
    {
        $this->get(route('login'))->assertDontSee('name="description"', false);
    }

    public function test_the_page_no_longer_offers_language_or_keywords(): void
    {
        $this->actingAs($this->admin())->get(route('admin.settings.general'))->assertOk()
            ->assertDontSee('name="locale"', false)->assertDontSee('name="site_keywords"', false)
            ->assertSee('id="site_description"', false)->assertDontSee('tinymce.min.js', false);

        $this->save(['site_description' => str_repeat('a', 301)])->assertSessionHasErrors('site_description');
    }

    public function test_the_migration_removes_the_unused_settings_and_flattens_an_old_description(): void
    {
        $settings = app(SettingService::class);
        $settings->set('locale', 'en');
        $settings->set('site_keywords', 'réservation, salle');
        $settings->set('site_description', '<p>Ancienne <em>description</em></p>');

        (require database_path('migrations/2026_11_26_000001_clean_unused_general_settings.php'))->up();

        $this->assertFalse(DB::table('settings')->whereIn('key', ['locale', 'site_keywords'])->exists());
        $this->assertSame('Ancienne description', setting('site_description'));
    }
}
