<?php

namespace Tests\Feature;

use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Pages d'erreur en francais, au nom du site, meme quand la base est indisponible. */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_missing_page_is_explained_in_french(): void
    {
        app(SettingService::class)->set('app_name', 'Médiathèque');

        $this->get('/cette-page-n-existe-pas')->assertNotFound()
            ->assertSee('Page introuvable')->assertSee('Médiathèque')->assertDontSee('Not Found');
    }

    public function test_every_common_error_has_a_french_page(): void
    {
        $expected = [403 => 'Accès refusé', 405 => 'Adresse incorrecte', 419 => 'Page expirée', 429 => 'Trop de tentatives', 500 => 'Une erreur est survenue', 503 => 'Service momentanément indisponible'];
        foreach ($expected as $status => $heading) {
            Route::get("/_test/erreur-{$status}", fn () => abort($status));
        }

        foreach ($expected as $status => $heading) {
            $this->get("/_test/erreur-{$status}")->assertStatus($status)->assertSee($heading, false)->assertSee('lang="fr"', false);
        }
    }

    public function test_the_error_page_still_renders_when_the_settings_cannot_be_read(): void
    {
        Schema::drop('settings');
        cache()->flush();

        $html = view('errors.500')->render();

        $this->assertStringContainsString('Une erreur est survenue', $html);
        $this->assertStringContainsString('NovaCreneaux', $html);
    }

    public function test_the_maintenance_page_uses_the_same_look_and_shows_the_message(): void
    {
        $html = view('errors.maintenance', ['message' => "Retour <b>lundi</b>"])->render();

        $this->assertStringContainsString('Site en maintenance', $html);
        $this->assertStringContainsString('Retour &lt;b&gt;lundi&lt;/b&gt;', $html);
    }

    public function test_validation_messages_are_complete_in_french(): void
    {
        $english = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $french = require lang_path('fr/validation.php');

        $this->assertSame([], array_values(array_diff(array_keys($english), array_keys($french))), 'regles sans traduction francaise');
        $this->assertSame('fr', config('app.locale'));
    }
}
