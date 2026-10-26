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

    public function test_webauthn_challenge_is_locked_after_repeated_failures_like_the_other_methods(): void
    {
        $user = $this->userWithTotp();

        for ($i = 0; $i < 5; $i++) {
            $this->withSession(['mfa_user_id' => $user->id, 'webauthn_assert_challenge' => base64_encode('defi')])
                ->post(route('mfa.challenge.webauthn'), ['assertion' => json_encode(['id' => 'inconnu'])])
                ->assertSessionHasErrors('key');
        }

        $response = $this->withSession(['mfa_user_id' => $user->id, 'webauthn_assert_challenge' => base64_encode('defi')])
            ->post(route('mfa.challenge.webauthn'), ['assertion' => json_encode(['id' => 'inconnu'])]);

        $response->assertSessionHasErrors('code');
        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('code'));
        $this->assertGuest();
    }

    public function test_webauthn_failures_share_the_counter_with_the_code_methods(): void
    {
        $user = $this->userWithTotp();
        for ($i = 0; $i < 3; $i++) {
            $this->withSession(['mfa_user_id' => $user->id])->post(route('mfa.challenge'), ['method' => 'totp', 'code' => '000000']);
        }
        for ($i = 0; $i < 2; $i++) {
            $this->withSession(['mfa_user_id' => $user->id, 'webauthn_assert_challenge' => base64_encode('defi')])
                ->post(route('mfa.challenge.webauthn'), ['assertion' => '{}']);
        }

        $this->withSession(['mfa_user_id' => $user->id])->post(route('mfa.challenge'), ['method' => 'totp', 'code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('code'));
    }

    public function test_the_webauthn_challenge_is_single_use_even_when_the_attempt_fails(): void
    {
        $user = $this->userWithTotp();

        $this->withSession(['mfa_user_id' => $user->id, 'webauthn_assert_challenge' => base64_encode('defi')])
            ->post(route('mfa.challenge.webauthn'), ['assertion' => json_encode(['id' => 'x'])]);

        $this->assertNull(session('webauthn_assert_challenge'));
        $this->post(route('mfa.challenge.webauthn'), ['assertion' => json_encode(['id' => 'x'])])
            ->assertSessionHasErrors('key');
        $this->assertStringContainsString('Session expirée', session('errors')->first('key'));
    }

    public function test_malformed_assertions_are_counted_as_failures_not_errors(): void
    {
        $user = $this->userWithTotp();

        foreach (['pas du json', '"texte"', '123', ''] as $assertion) {
            $this->withSession(['mfa_user_id' => $user->id, 'webauthn_assert_challenge' => base64_encode('defi')])
                ->post(route('mfa.challenge.webauthn'), ['assertion' => $assertion])
                ->assertSessionHasErrors('key');
        }
        $this->assertGuest();
    }
}
