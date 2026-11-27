<?php

namespace Tests\Feature;

use App\Services\SettingService;
use App\Support\SiteLogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Le logo est garde dans storage et servi par le site : il s'affiche quelle que soit la configuration du serveur web. */
class SiteLogoTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private function upload(): void
    {
        $this->actingAs($this->admin())->post(route('admin.settings.logo.upload'), ['logo' => UploadedFile::fake()->image('logo.png', 120, 40)])
            ->assertSessionHas('success', 'Logo mis à jour.');
    }

    public function test_an_uploaded_logo_is_served_by_the_site_to_everyone(): void
    {
        $before = glob(public_path('uploads/logo/*')) ?: [];
        $this->upload();
        $url = SiteLogo::url();

        $this->assertStringStartsWith(route('site.logo'), $url);
        $this->assertSame($before, glob(public_path('uploads/logo/*')) ?: [], 'rien de nouveau dans public/');
        auth()->logout();
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
        $this->get(route('admin.settings.general'))->assertRedirect();
        $this->actingAs($this->admin())->get(route('admin.settings.general'))->assertOk()->assertSee($url, false);
    }

    public function test_a_new_logo_replaces_the_old_file_and_changes_the_address(): void
    {
        $this->upload();
        [$first, $firstUrl] = [SiteLogo::path(), SiteLogo::url()];

        $this->upload();

        $this->assertFileDoesNotExist($first);
        $this->assertNotSame($firstUrl, SiteLogo::url(), 'le navigateur ne garde pas l\'ancien en cache');
        $this->assertCount(1, glob(SiteLogo::directory() . '/*'));
    }

    public function test_deleting_the_logo_removes_the_file_and_the_address_answers_404(): void
    {
        $this->upload();
        $path = SiteLogo::path();

        $this->delete(route('admin.settings.logo.delete'))->assertSessionHas('success', 'Logo supprimé.');

        $this->assertFileDoesNotExist($path);
        $this->assertNull(SiteLogo::url());
        $this->get(route('site.logo'))->assertNotFound();
    }

    public function test_the_migration_moves_an_old_logo_out_of_public(): void
    {
        $name = 'logo_' . bin2hex(random_bytes(8)) . '.png';
        @mkdir(public_path('uploads/logo'), 0755, true);
        file_put_contents(public_path("uploads/logo/{$name}"), 'png');
        app(SettingService::class)->set('site_logo', "uploads/logo/{$name}");

        (require database_path('migrations/2026_11_28_000003_move_site_logo_to_storage.php'))->up();

        $this->assertFileDoesNotExist(public_path("uploads/logo/{$name}"));
        $this->assertSame(SiteLogo::directory() . "/{$name}", SiteLogo::path());

        // Un ancien logo introuvable est oublie
        app(SettingService::class)->set('site_logo', 'uploads/logo/logo_0000000000000000.png');
        (require database_path('migrations/2026_11_28_000003_move_site_logo_to_storage.php'))->up();
        $this->assertSame('', DB::table('settings')->where('key', 'site_logo')->value('value'));
    }
}
