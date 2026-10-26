<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiTokenCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function serviceAccount(): User
    {
        return User::factory()->create(['email' => 'borne@example.com', 'role_id' => Role::defaultUser()->id]);
    }

    /** Cree un jeton par la commande et renvoie le secret affiche une seule fois. */
    private function createToken(array $arguments): string
    {
        $this->assertSame(0, Artisan::call('api:token:create', $arguments));
        preg_match('/(ncr_\S+)/', Artisan::output(), $match);

        return $match[1];
    }

    public function test_a_created_token_works_until_it_is_revoked(): void
    {
        $this->serviceAccount();

        $token = $this->createToken(['email' => 'borne@example.com', 'name' => 'Borne CDI']);

        $this->getJson('/api/v1/resources', ['Authorization' => "Bearer {$token}"])->assertOk();

        $id = PersonalAccessToken::first()->id;
        $this->artisan('api:token:revoke', ['id' => $id])->assertSuccessful();

        $this->app['auth']->forgetGuards(); // chaque requete reelle repart d'un garde vierge
        $this->getJson('/api/v1/resources', ['Authorization' => "Bearer {$token}"])->assertStatus(401);
    }

    public function test_the_token_is_stored_hashed_with_only_the_requested_abilities(): void
    {
        $this->serviceAccount();

        $token = $this->createToken(['email' => 'borne@example.com', 'name' => 'Lecture', '--ability' => ['resources:read']]);
        $stored = PersonalAccessToken::first();

        $this->assertNotSame($token, $stored->token);
        $this->assertStringNotContainsString($token, $stored->token);
        $this->assertSame(['resources:read'], $stored->abilities);
    }

    public function test_unknown_abilities_and_accounts_are_refused(): void
    {
        $this->serviceAccount();

        $this->artisan('api:token:create', ['email' => 'borne@example.com', 'name' => 'x', '--ability' => ['users:delete']])->assertFailed();
        $this->artisan('api:token:create', ['email' => 'inconnu@example.com', 'name' => 'x'])->assertFailed();
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_a_deactivated_account_cannot_receive_a_token(): void
    {
        $this->serviceAccount()->update(['is_active' => false]);

        $this->artisan('api:token:create', ['email' => 'borne@example.com', 'name' => 'x'])->assertFailed();
    }

    public function test_the_list_never_prints_the_secret(): void
    {
        $this->serviceAccount();
        $token = $this->createToken(['email' => 'borne@example.com', 'name' => 'Borne CDI']);

        $this->artisan('api:token:list')->doesntExpectOutputToContain($token)->expectsOutputToContain('borne@example.com')->assertSuccessful();
    }
}
