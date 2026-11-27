<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Passer d'un utilisateur a un autre dans un meme test : une session reelle n'appartient qu'a un compte (le middleware
     * EndSessionsOnPasswordChange ferme une session dont l'empreinte du mot de passe ne correspond plus), on repart donc d'une session neuve.
     */
    public function actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, $guard = null)
    {
        $current = $this->app['auth']->guard('web')->user();
        if ($current !== null && $current->getAuthIdentifier() !== $user->getAuthIdentifier()) {
            $this->flushSession();
        }

        return parent::actingAs($user, $guard);
    }

    /** Verrou d'installation et dossier du logo propres a chaque test : ceux du site ne sont jamais touches. */
    private ?string $installLock = null;
    private ?string $logoDirectory = null;

    protected function setUp(): void
    {
        parent::setUp();
        $id = bin2hex(random_bytes(6));
        $this->installLock = sys_get_temp_dir() . "/nc-installed-{$id}.lock";
        $this->logoDirectory = sys_get_temp_dir() . "/nc-logo-{$id}";
        config(['nova.install_lock' => $this->installLock, 'nova.logo_directory' => $this->logoDirectory]);
    }

    protected function tearDown(): void
    {
        if ($this->installLock !== null) {
            @unlink($this->installLock);
        }
        foreach (glob($this->logoDirectory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir((string) $this->logoDirectory);
        parent::tearDown();
    }
}
