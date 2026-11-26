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

    /** Verrou d'installation propre a chaque test : celui du site (storage/app/installed.lock) n'est jamais touche. */
    private ?string $installLock = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installLock = sys_get_temp_dir() . '/nc-installed-' . bin2hex(random_bytes(6)) . '.lock';
        config(['nova.install_lock' => $this->installLock]);
    }

    protected function tearDown(): void
    {
        if ($this->installLock !== null) {
            @unlink($this->installLock);
        }
        parent::tearDown();
    }
}
