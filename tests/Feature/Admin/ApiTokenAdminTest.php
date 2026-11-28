<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiTokenAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id'), 'is_admin' => true]);
    }

    private function serviceAccount(): User
    {
        return User::factory()->create(['email' => 'borne@example.com', 'role_id' => Role::defaultUser()->id]);
    }

    public function test_only_administrators_can_manage_tokens(): void
    {
        $student = $this->serviceAccount();

        $this->get(route('admin.api-tokens.index'))->assertRedirect(route('login'));
        $this->actingAs($student)->get(route('admin.api-tokens.index'))->assertRedirect('/');
        $this->actingAs($student)->post(route('admin.api-tokens.store'), ['user_id' => $student->id, 'name' => 'x', 'abilities' => ['resources:read']])->assertRedirect('/');
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_a_token_is_created_shown_once_and_works(): void
    {
        $account = $this->serviceAccount();

        $response = $this->actingAs($this->admin())->post(route('admin.api-tokens.store'), [
            'user_id' => $account->id, 'name' => 'Borne CDI', 'abilities' => ['resources:read'], 'days' => 30,
        ]);
        $response->assertRedirect(route('admin.api-tokens.index'));
        $secret = session('new_token');
        $this->assertMatchesRegularExpression("/^\\d+\\|ncr_/", $secret);

        $this->get(route('admin.api-tokens.index'))->assertOk()->assertSee($secret, false)->assertSee('Borne CDI');
        $this->get(route('admin.api-tokens.index'))->assertOk()->assertDontSee($secret, false);

        $stored = PersonalAccessToken::firstOrFail();
        $this->assertNotSame($secret, $stored->token);
        $this->assertSame(['resources:read'], $stored->abilities);
        $this->assertTrue($stored->expires_at->between(now()->addDays(29), now()->addDays(31)));
        $this->assertTrue(ActivityLog::where('action', 'Jeton API créé')->exists());

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$secret}"])->assertOk()->assertJsonPath('data.name', $account->name);
    }

    public function test_the_form_is_validated(): void
    {
        $admin = $this->admin();
        $account = $this->serviceAccount();

        $this->actingAs($admin)->post(route('admin.api-tokens.store'), ['user_id' => $account->id, 'name' => '', 'abilities' => []])
            ->assertSessionHasErrors(['name', 'abilities']);
        $this->post(route('admin.api-tokens.store'), ['user_id' => $account->id, 'name' => 'x', 'abilities' => ['users:delete']])
            ->assertSessionHasErrors('abilities.0');
        $this->post(route('admin.api-tokens.store'), ['user_id' => $account->id, 'name' => 'x', 'abilities' => ['resources:read'], 'days' => 9999])
            ->assertSessionHasErrors('days');
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_a_deactivated_account_cannot_receive_a_token(): void
    {
        $account = $this->serviceAccount();
        $account->update(['is_active' => false]);

        $this->actingAs($this->admin())->post(route('admin.api-tokens.store'), ['user_id' => $account->id, 'name' => 'x', 'abilities' => ['resources:read']])
            ->assertSessionHas('error');
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_revoking_a_token_stops_it_immediately(): void
    {
        $account = $this->serviceAccount();
        $plain = $account->createToken('Borne', ['resources:read'])->plainTextToken;
        $id = PersonalAccessToken::firstOrFail()->id;

        $this->actingAs($this->admin())->delete(route('admin.api-tokens.destroy', $id))->assertRedirect(route('admin.api-tokens.index'));

        $this->assertSame(0, PersonalAccessToken::count());
        $this->assertTrue(ActivityLog::where('action', 'Jeton API révoqué')->exists());
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$plain}"])->assertStatus(401);
    }

    public function test_the_admin_menu_links_to_the_integrations_page(): void
    {
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()->assertSee('Intégrations API');
    }
}
