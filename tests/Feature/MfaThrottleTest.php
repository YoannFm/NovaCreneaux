<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MfaThrottleTest extends TestCase
{
    use RefreshDatabase;

    private function userWithTotp(): User
    {
        return User::factory()->create([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function test_totp_challenge_is_locked_after_repeated_failures(): void
    {
        $user = $this->userWithTotp();
        $session = ['mfa_user_id' => $user->id];

        for ($i = 0; $i < 5; $i++) {
            $this->withSession($session)->post(route('mfa.challenge'), ['method' => 'totp', 'code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        $response = $this->withSession($session)->post(route('mfa.challenge'), ['method' => 'totp', 'code' => '000000']);

        $response->assertSessionHasErrors('code');
        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('code'));
    }

    public function test_recovery_challenge_is_locked_after_repeated_failures(): void
    {
        $user = $this->userWithTotp();
        $session = ['mfa_user_id' => $user->id];

        for ($i = 0; $i < 5; $i++) {
            $this->withSession($session)->post(route('mfa.challenge.recovery'), ['code' => 'wrong-code']);
        }

        $this->withSession($session)->post(route('mfa.challenge.recovery'), ['code' => 'wrong-code'])
            ->assertSessionHasErrors('code');
        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('code'));
    }
}
