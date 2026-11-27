<?php

namespace Tests\Feature;

use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Un code de l'application d'authentification ne sert qu'une fois, meme pendant sa fenetre de validite. */
class TotpReplayTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private const SECRET = 'JBSWY3DPEHPK3PXP';

    /** Code de l'application pour la periode courante (+ $offset periodes de 30 s), comme le service le calcule (horloge du systeme). */
    private function code(int $offset = 0): string
    {
        $generate = new \ReflectionMethod(TotpService::class, 'generateCode');

        return $generate->invoke(app(TotpService::class), self::SECRET, (int) floor(time() / 30) + $offset);
    }

    public function test_an_authenticator_code_works_only_once(): void
    {
        $user = $this->student(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()]);
        $code = $this->code();

        $this->withSession(['mfa_user_id' => $user->id])->post(route('mfa.challenge'), ['method' => 'totp', 'code' => $code])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $this->withSession(['mfa_user_id' => $user->id])->post(route('mfa.challenge'), ['method' => 'totp', 'code' => $code])->assertSessionHasErrors();
        $this->assertGuest();

        // Le code suivant (toujours dans la fenetre acceptee) passe : seul un code deja utilise, ou plus ancien, est refuse
        $this->withSession(['mfa_user_id' => $user->id])->post(route('mfa.challenge'), ['method' => 'totp', 'code' => $this->code(1)])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }
}
