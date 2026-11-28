<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class PersonalApiTokenTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private const PASSWORD = 'MotDePasse-Eleve-1';

    private function pupil(): User
    {
        return User::factory()->create(['role_id' => Role::defaultUser()->id, 'password' => bcrypt(self::PASSWORD)]);
    }

    private function payload(array $override = []): array
    {
        return $override + ['name' => 'Mon appli', 'abilities' => ['resources:read', 'reservations:read'], 'days' => 90, 'password' => self::PASSWORD];
    }

    private function denyApiTo(User $user): void
    {
        $user->role->update(['api_access' => false]);
    }

    public function test_a_pupil_creates_a_token_that_is_shown_once_and_works_on_the_api(): void
    {
        $pupil = $this->pupil();

        $this->actingAs($pupil)->get(route('profile.edit'))->assertOk()->assertSee("Jetons d'API", false);
        $this->post(route('profile.api-tokens.store'), $this->payload())->assertRedirect(route('profile.edit') . '#api');
        $secret = session('new_token');
        $this->assertMatchesRegularExpression('/^\d+\|ncr_/', $secret);

        $this->get(route('profile.edit'))->assertSee($secret, false)->assertSee('Mon appli');
        $this->get(route('profile.edit'))->assertDontSee($secret, false);

        $token = $pupil->tokens()->firstOrFail();
        $this->assertSame(['resources:read', 'reservations:read'], $token->abilities);
        $this->assertEqualsWithDelta(now()->addDays(90)->timestamp, $token->expires_at->timestamp, 5);
        $this->assertTrue(ActivityLog::where('action', 'Jeton API créé')->where('user_id', $pupil->id)->exists());

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->withHeader('Authorization', 'Bearer ' . $secret)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $pupil->id);
    }

    public function test_the_current_password_is_required(): void
    {
        $pupil = $this->pupil();

        foreach (['', 'mauvais'] as $password) {
            $this->actingAs($pupil)->post(route('profile.api-tokens.store'), $this->payload(['password' => $password]))
                ->assertSessionHasErrors('password', errorBag: 'apiToken');
        }
        $this->assertSame(0, $pupil->tokens()->count());
    }

    public function test_the_form_is_validated(): void
    {
        $pupil = $this->pupil();

        foreach ([
            ['abilities' => []],
            ['abilities' => ['admin:everything']],
            ['days' => 3650],
            ['days' => 0],
            ['name' => ''],
        ] as $invalid) {
            $this->actingAs($pupil)->post(route('profile.api-tokens.store'), $this->payload($invalid))->assertSessionHasErrorsIn('apiToken');
        }
        $this->assertSame(0, $pupil->tokens()->count());
    }

    public function test_the_number_of_active_tokens_is_limited_but_expired_ones_do_not_count(): void
    {
        $pupil = $this->pupil();
        $service = app(ApiTokenService::class);
        $service->create($pupil, 'ancien', ['resources:read'], 1)->accessToken->forceFill(['expires_at' => now()->subDay()])->save();
        for ($i = 0; $i < ApiTokenService::PERSONAL_LIMIT; $i++) {
            $this->actingAs($pupil)->post(route('profile.api-tokens.store'), $this->payload(['name' => "jeton {$i}"]))->assertSessionMissing('api_error');
        }

        $this->post(route('profile.api-tokens.store'), $this->payload())->assertSessionHas('api_error');

        $this->assertSame(ApiTokenService::PERSONAL_LIMIT + 1, $pupil->tokens()->count());
    }

    public function test_a_pupil_revokes_only_their_own_tokens(): void
    {
        $pupil = $this->pupil();
        $other = $this->pupil();
        $mine = app(ApiTokenService::class)->create($pupil, 'mien', ['resources:read'], 30)->accessToken;
        $theirs = app(ApiTokenService::class)->create($other, 'autre', ['resources:read'], 30)->accessToken;

        $this->actingAs($pupil)->delete(route('profile.api-tokens.destroy', $theirs->id))->assertNotFound();
        $this->assertNotNull(PersonalAccessToken::find($theirs->id));

        $this->delete(route('profile.api-tokens.destroy', $mine->id))->assertRedirect(route('profile.edit') . '#api');
        $this->assertNull(PersonalAccessToken::find($mine->id));
        $this->assertTrue(ActivityLog::where('action', 'Jeton API révoqué')->exists());
    }

    public function test_a_role_without_api_access_gets_no_token_section_and_cannot_create_tokens(): void
    {
        $pupil = $this->pupil();
        $this->denyApiTo($pupil);

        $this->actingAs($pupil)->get(route('profile.edit'))->assertOk()->assertDontSee("Jetons d'API", false);
        $this->post(route('profile.api-tokens.store'), $this->payload())->assertForbidden();
        $this->assertSame(0, $pupil->tokens()->count());
    }

    public function test_existing_tokens_stop_working_when_the_role_loses_api_access_and_work_again_after(): void
    {
        $pupil = $this->pupil();
        $secret = app(ApiTokenService::class)->create($pupil, 'appli', ['resources:read'], 30)->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $secret)->getJson('/api/v1/me')->assertOk();

        $this->denyApiTo($pupil);
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->withHeader('Authorization', 'Bearer ' . $secret)->getJson('/api/v1/me')->assertForbidden()->assertJsonPath('error.code', 'api_access_denied');
        $this->assertSame(1, $pupil->tokens()->count());

        $pupil->role->update(['api_access' => true]);
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->withHeader('Authorization', 'Bearer ' . $secret)->getJson('/api/v1/me')->assertOk();
    }

    public function test_the_administration_does_not_create_tokens_for_such_a_role(): void
    {
        $pupil = $this->pupil();
        $this->denyApiTo($pupil);

        $this->actingAs($this->admin())->get(route('admin.api-tokens.index'))->assertOk()->assertDontSee($pupil->email);
        $this->post(route('admin.api-tokens.store'), ['user_id' => $pupil->id, 'name' => 'x', 'abilities' => ['resources:read']])->assertSessionHas('error');
        $this->assertSame(0, PersonalAccessToken::count());
        $this->artisan('api:token:create', ['email' => $pupil->email, 'name' => 'x'])->assertFailed();
    }

    public function test_the_role_form_controls_api_access(): void
    {
        $payload = ['label' => 'Externe', 'color' => '#3b7ddd', 'max_reservations_day' => 2, 'max_reservations_week' => 5, 'cancel_deadline_minutes' => 30, 'advance_days' => 14];
        $this->actingAs($this->admin());

        $this->post(route('admin.roles.store'), $payload + ['name' => 'externe', 'api_access' => '0'])->assertSessionHasNoErrors();
        $role = Role::where('name', 'externe')->first();
        $this->assertFalse($role->api_access);

        $this->post(route('admin.roles.store'), $payload + ['name' => 'invite'])->assertSessionHasNoErrors();
        $this->assertTrue(Role::where('name', 'invite')->first()->api_access, 'sans le champ, le role garde l\'acces');

        $this->put(route('admin.roles.update', $role), $payload)->assertSessionHasNoErrors();
        $this->assertFalse($role->fresh()->api_access, 'un envoi sans le champ ne change pas le reglage');
        $this->put(route('admin.roles.update', $role), $payload + ['api_access' => '1']);
        $this->assertTrue($role->fresh()->api_access);

        $this->put(route('admin.roles.update', $role), $payload + ['api_access' => '0']);
        $this->get(route('admin.roles.index'))->assertSee('Sans API');
        $this->get(route('admin.roles.edit', $role))->assertSee('Accès à l');
    }

    public function test_every_role_has_api_access_after_the_migration(): void
    {
        $this->assertSame(0, Role::where('api_access', false)->count());
    }

    public function test_the_global_switch_cuts_the_api_for_everyone_and_hides_the_token_section(): void
    {
        $pupil = $this->pupil();
        $secret = app(ApiTokenService::class)->create($pupil, 'appli', ['resources:read'], 30)->plainTextToken;
        $admin = $this->admin();

        $this->actingAs($admin, 'web')->put(route('admin.api-tokens.status'), ['enabled' => '0'])->assertSessionHas('success');
        $this->assertTrue(ActivityLog::where('action', 'API désactivée')->exists());

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->withHeader('Authorization', 'Bearer ' . $secret)->getJson('/api/v1/me')->assertStatus(503)->assertJsonPath('error.code', 'api_disabled');
        $this->getJson('/api/v1/meta')->assertStatus(503)->assertJsonPath('error.code', 'api_disabled');
        $this->actingAs($pupil, 'web')->get(route('profile.edit'))->assertDontSee("Jetons d'API", false);
        $this->post(route('profile.api-tokens.store'), $this->payload())->assertForbidden();
        $this->actingAs($admin, 'web')->post(route('admin.api-tokens.store'), ['user_id' => $pupil->id, 'name' => 'x', 'abilities' => ['resources:read']])->assertSessionHas('error');
        $this->get(route('admin.api-tokens.index'))->assertSee('Désactivée')->assertSee("Activer l'API");

        $this->put(route('admin.api-tokens.status'), ['enabled' => '1'])->assertSessionHas('success');
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->withHeader('Authorization', 'Bearer ' . $secret)->getJson('/api/v1/me')->assertOk();
        $this->getJson('/api/v1/meta')->assertOk();
    }

    public function test_only_administrators_flip_the_global_switch(): void
    {
        $this->actingAs($this->pupil())->put(route('admin.api-tokens.status'), ['enabled' => '0'])->assertRedirect('/');
        $this->assertTrue(ApiTokenService::enabled());
    }
}
