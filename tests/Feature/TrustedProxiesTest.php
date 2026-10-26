<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Rules\TrustedProxyList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TrustProxies::flushState();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id'), 'is_admin' => true]);
    }

    private function seenIpBehindProxy(): string
    {
        (new AppServiceProvider($this->app))->boot();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
            ->getJson('/api/v1/meta');

        return request()->ip();
    }

    public function test_forwarded_headers_are_ignored_by_default(): void
    {
        $this->assertSame('127.0.0.1', $this->seenIpBehindProxy());
    }

    public function test_a_declared_proxy_gives_the_real_visitor_address(): void
    {
        Setting::updateOrCreate(['key' => 'trusted_proxies'], ['value' => '127.0.0.1']);
        cache()->forget('setting_trusted_proxies');

        $this->assertSame('203.0.113.9', $this->seenIpBehindProxy());
    }

    public function test_the_list_accepts_addresses_networks_and_star_only(): void
    {
        $valid = fn ($value) => Validator::make(['p' => $value], ['p' => [new TrustedProxyList]])->passes();

        $this->assertTrue($valid('127.0.0.1'));
        $this->assertTrue($valid('10.0.0.0/8, ::1, 2001:db8::/32'));
        $this->assertTrue($valid('*'));
        $this->assertTrue($valid(''));
        $this->assertFalse($valid('pas-une-ip'));
        $this->assertFalse($valid('10.0.0.0/40'));
        $this->assertFalse($valid('127.0.0.1, evil'));
    }

    public function test_the_admin_saves_a_clean_list_and_sees_the_diagnostic(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.settings.security.proxies'), ['trusted_proxies' => ' 127.0.0.1 ,127.0.0.1, 10.0.0.0/8 '])
            ->assertSessionHasNoErrors();
        $this->assertSame('127.0.0.1,10.0.0.0/8', Setting::find('trusted_proxies')->value);

        $this->get(route('admin.settings.security'))->assertOk()->assertSee('Proxys de confiance')->assertSee('X-Forwarded-For', false);
    }

    public function test_an_invalid_list_is_refused_and_not_saved(): void
    {
        $this->actingAs($this->admin())->post(route('admin.settings.security.proxies'), ['trusted_proxies' => 'pas-une-ip'])
            ->assertSessionHasErrors('trusted_proxies');

        $this->assertNull(Setting::find('trusted_proxies'));
    }

    public function test_students_cannot_change_the_proxies(): void
    {
        $student = User::factory()->create(['role_id' => Role::defaultUser()->id]);

        $this->actingAs($student)->post(route('admin.settings.security.proxies'), ['trusted_proxies' => '*'])->assertRedirect('/');
        $this->assertNull(Setting::find('trusted_proxies'));
    }

    public function test_a_public_connection_is_never_suggested_as_a_proxy(): void
    {
        $this->actingAs($this->admin())->withServerVariables(['REMOTE_ADDR' => '86.249.237.194'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
            ->get(route('admin.settings.security'))
            ->assertOk()->assertSee('aucun proxy à déclarer')->assertDontSee('Saisissez');
    }

    public function test_the_page_suggests_the_proxy_when_the_real_address_is_ignored(): void
    {
        $this->actingAs($this->admin())->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
            ->get(route('admin.settings.security'))
            ->assertOk()->assertSee('Saisissez');
    }
}
