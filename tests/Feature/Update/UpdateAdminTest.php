<?php

namespace Tests\Feature\Update;

use App\Models\Role;
use App\Models\User;
use App\Services\Update\UpdateManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Tests\Support\BuildsReleases;
use Tests\TestCase;

class UpdateAdminTest extends TestCase
{
    use BuildsReleases, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRelease();
        Cache::forget('update_check');
        // Toute l'application utilise le site factice (dossiers temporaires), jamais le vrai
        $this->app->bind(UpdateManager::class, fn () => $this->manager());
    }

    protected function tearDown(): void
    {
        $this->tearDownRelease();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id'), 'is_admin' => true]);
    }

    private function publish(array $apiOverrides = [], ?string $secretKey = null): void
    {
        [$zip, $signature] = $this->buildZip(['app/Old.php' => 'nouveau', 'app/New.php' => 'ajoute'], ['deleted' => ['app/Removed.php']], $secretKey);
        $this->fakeGithub($zip, $signature, '1.1.0', $apiOverrides);
    }

    public function test_only_administrators_reach_the_update_pages(): void
    {
        $student = User::factory()->create(['role_id' => Role::defaultUser()->id]);

        $this->get(route('admin.update.index'))->assertRedirect(route('login'));
        $this->post(route('admin.update.install'))->assertRedirect(route('login'));
        $this->actingAs($student)->get(route('admin.update.index'))->assertRedirect('/');
        $this->actingAs($student)->postJson(route('admin.update.install'))->assertRedirect('/');
    }

    public function test_the_page_shows_the_current_version_when_up_to_date(): void
    {
        Http::fake(['api.github.com/*' => Http::response([], 404)]);

        $this->actingAs($this->admin())->get(route('admin.update.index'))->assertOk()
            ->assertSee('v1.0.0')->assertSee('dernière version')->assertSee("Aucune version n'a encore été publiée", false);
    }

    public function test_an_available_update_shows_its_changelog_escaped_and_the_download_button(): void
    {
        $this->publish(['body' => '<script>alert(1)</script> Nouveautes']);

        $this->actingAs($this->admin())->get(route('admin.update.index'))->assertOk()
            ->assertSee('Version 1.1.0 disponible')->assertSee('sauvegarde de la base de données')
            ->assertSee('Télécharger la version 1.1.0')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; Nouveautes', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_a_missing_signing_key_is_explained_and_blocks_the_download_button(): void
    {
        $this->publish();
        config(['nova.update_public_key' => '']);

        $html = $this->actingAs($this->admin())->get(route('admin.update.index'))->assertOk()
            ->assertSee('Mises à jour désactivées')->getContent();

        $this->assertMatchesRegularExpression('/data-update-route="[^"]*download"[^>]*disabled/s', $html);
    }

    public function test_the_full_flow_through_the_admin_endpoints(): void
    {
        $this->publish();
        $this->actingAs($this->admin());

        $this->postJson(route('admin.update.download'))->assertOk()->assertJsonPath('data.version', '1.1.0');
        $this->get(route('admin.update.index'))->assertSee('Installer la version 1.1.0');

        $this->postJson(route('admin.update.install'))->assertOk()->assertJsonPath('data.to', '1.1.0');
        $this->get(route('admin.update.index'))->assertSee('Mise à jour à finaliser')->assertSee('Terminer la mise à jour');

        $this->postJson(route('admin.update.finalize'))->assertOk()->assertSessionHas('success');
        $this->assertSame('nouveau', file_get_contents("{$this->siteDir}/app/Old.php"));
        $this->get(route('admin.update.index'))->assertSee('Revenir en arrière');

        $this->postJson(route('admin.update.restore'))->assertOk();
        $this->assertSame('ancien', file_get_contents("{$this->siteDir}/app/Old.php"));
    }

    public function test_failures_are_returned_as_readable_json_errors(): void
    {
        $this->publish(secretKey: base64_encode(sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair())));
        $this->actingAs($this->admin());

        $this->postJson(route('admin.update.download'))->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'signature'));
        $this->postJson(route('admin.update.finalize'))->assertStatus(422)->assertJsonPath('message', 'Aucune mise à jour à finaliser.');
        $this->postJson(route('admin.update.restore'))->assertStatus(422);
    }

    public function test_unexpected_errors_do_not_leak_a_stack_trace(): void
    {
        $this->mock(UpdateManager::class)->shouldReceive('download')->andThrow(new \RuntimeException('boom'));

        $response = $this->actingAs($this->admin())->postJson(route('admin.update.download'))->assertStatus(500);

        $this->assertSame('Erreur inattendue : boom', $response->json('message'));
        $this->assertArrayNotHasKey('trace', $response->json());
    }

    public function test_the_check_button_forces_a_new_lookup(): void
    {
        $this->publish();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.update.check'))->assertSessionHas('success', 'La version 1.1.0 est disponible.');

        Http::swap(new HttpFactory());
        Http::fake(['api.github.com/*' => Http::response([], 500)]);
        $this->post(route('admin.update.check'))->assertSessionHas('error');
    }

    public function test_the_sidebar_flags_an_available_update(): void
    {
        $this->publish();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.update.index'));

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Une nouvelle version est disponible');
    }

    public function test_the_write_endpoints_are_rate_limited(): void
    {
        $this->publish();
        $this->actingAs($this->admin());

        $statuses = [];
        for ($i = 0; $i < 11; $i++) {
            $statuses[] = $this->postJson(route('admin.update.finalize'))->status();
        }

        $this->assertSame(422, $statuses[9]);
        $this->assertSame(429, $statuses[10]);
    }
}
