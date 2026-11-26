<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\EnforceAdminTwoFactor;
use App\Http\Middleware\EnforceEmailVerification;
use App\Http\Middleware\EnforceMaintenanceMode;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Installation neuve : la base est vide ou injoignable. L'assistant doit s'afficher quand meme, aucun controle
 * commun a toutes les pages (maintenance, double authentification, adresse confirmee) ne doit lire la base avant lui.
 */
class InstallWithoutDatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // En test, CheckInstallation considere toujours le site installe : on le retire pour ouvrir l'assistant
        $this->withoutMiddleware(CheckInstallation::class);
        config([
            'database.connections.injoignable' => ['driver' => 'sqlite', 'database' => '/dossier/inexistant/base.sqlite', 'prefix' => ''],
            'database.default' => 'injoignable',
        ]);
        DB::purge('injoignable');
        Cache::flush();
    }

    public function test_the_installer_opens_when_the_database_cannot_be_reached(): void
    {
        $this->get(route('install.index'))->assertOk();
        $this->get(route('install.prerequisites'))->assertOk()->assertSee('Extension zip');
        $this->get(route('install.db-config'))->assertOk();
    }

    /** Chaque controle, pris seul : un visiteur non connecte sur l'assistant passe sans aucune lecture en base. */
    public function test_each_site_wide_check_lets_a_guest_reach_the_installer_without_reading_the_database(): void
    {
        foreach ([EnforceMaintenanceMode::class, EnforceAdminTwoFactor::class, EnforceEmailVerification::class] as $middleware) {
            $response = app($middleware)->handle(Request::create('/install'), fn () => new Response('suite'));

            $this->assertSame('suite', $response->getContent(), $middleware);
        }
    }
}
