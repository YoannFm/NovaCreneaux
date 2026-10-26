<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecretSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'S3cret-valeur/avec+symboles==';

    private function settings(): SettingService
    {
        return app(SettingService::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id'), 'is_admin' => true]);
    }

    private function stored(string $key): ?string
    {
        return Setting::find($key)?->value;
    }

    public function test_a_secret_is_encrypted_in_the_database_and_read_back_in_clear(): void
    {
        $this->settings()->set('mail_smtp_password', self::SECRET);

        $this->assertStringStartsWith('enc:v1:', $this->stored('mail_smtp_password'));
        $this->assertStringNotContainsString(self::SECRET, $this->stored('mail_smtp_password'));
        $this->assertSame(self::SECRET, $this->settings()->get('mail_smtp_password'));
        $this->assertSame(self::SECRET, setting('mail_smtp_password'));
    }

    public function test_the_cache_never_holds_a_secret_in_clear(): void
    {
        $this->settings()->set('sms_ntfy_token', self::SECRET);
        $this->settings()->get('sms_ntfy_token');

        $this->assertStringStartsWith('enc:v1:', Cache::get('setting_sms_ntfy_token'));
    }

    public function test_other_settings_stay_in_clear(): void
    {
        $this->settings()->set('mail_smtp_host', 'smtp.example.com');

        $this->assertSame('smtp.example.com', $this->stored('mail_smtp_host'));
        $this->assertSame('smtp.example.com', $this->settings()->get('mail_smtp_host'));
    }

    public function test_an_empty_secret_is_stored_empty_not_encrypted(): void
    {
        $this->settings()->set('sms_android_pass', '');

        $this->assertSame('', $this->stored('sms_android_pass'));
        $this->assertSame('', $this->settings()->get('sms_android_pass'));
    }

    public function test_a_secret_saved_in_clear_before_the_encryption_is_still_readable(): void
    {
        Setting::create(['key' => 'sms_free_mobile_key', 'value' => 'ancienne-valeur']);

        $this->assertSame('ancienne-valeur', $this->settings()->get('sms_free_mobile_key'));
    }

    public function test_a_secret_unreadable_with_another_app_key_reads_as_its_default_without_failing(): void
    {
        $this->settings()->set('mail_smtp_password', self::SECRET);
        Cache::flush();

        config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);
        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');

        $this->assertNull($this->settings()->get('mail_smtp_password'));
        $this->assertSame('', $this->settings()->get('mail_smtp_password', ''));
    }

    public function test_all_leaves_secrets_out_and_status_reports_only_whether_they_are_set(): void
    {
        $this->settings()->set('mail_smtp_host', 'smtp.example.com');
        $this->settings()->set('mail_smtp_password', self::SECRET);
        $this->settings()->set('sms_ntfy_token', '');

        $all = $this->settings()->all();
        $status = $this->settings()->secretsStatus();

        $this->assertSame('smtp.example.com', $all['mail_smtp_host']);
        $this->assertArrayNotHasKey('mail_smtp_password', $all);
        $this->assertTrue($status['mail_smtp_password']);
        $this->assertFalse($status['sms_ntfy_token']);
        $this->assertFalse($status['sms_free_mobile_key']);
        $this->assertSame(SettingService::SECRET_KEYS, array_keys($status));
    }

    public function test_the_migration_encrypts_existing_secrets_once_and_can_be_reversed(): void
    {
        $migration = require database_path('migrations/2026_10_27_000001_encrypt_secret_settings.php');
        DB::table('settings')->insert([
            ['key' => 'mail_smtp_password', 'value' => 'en-clair'],
            ['key' => 'sms_ntfy_token', 'value' => ''],
            ['key' => 'mail_smtp_host', 'value' => 'smtp.example.com'],
        ]);

        $migration->up();
        $encrypted = $this->stored('mail_smtp_password');
        $migration->up();

        $this->assertStringStartsWith('enc:v1:', $encrypted);
        $this->assertSame($encrypted, $this->stored('mail_smtp_password'));
        $this->assertSame('', $this->stored('sms_ntfy_token'));
        $this->assertSame('smtp.example.com', $this->stored('mail_smtp_host'));
        $this->assertSame('en-clair', $this->settings()->get('mail_smtp_password'));

        $migration->down();

        $this->assertSame('en-clair', $this->stored('mail_smtp_password'));
    }

    public function test_the_forms_never_send_a_saved_secret_back_to_the_browser(): void
    {
        $this->settings()->set('mail_smtp_password', self::SECRET);
        $this->settings()->set('sms_free_mobile_key', self::SECRET);
        $admin = $this->admin();

        $mail = $this->actingAs($admin)->get(route('admin.settings.mail'))->assertOk();
        $security = $this->actingAs($admin)->get(route('admin.settings.security'))->assertOk();

        foreach ([$mail, $security] as $page) {
            $page->assertDontSee(self::SECRET, false)->assertDontSee('enc:v1:', false);
        }
        $mail->assertSee('Enregistré, inchangé')->assertSee('clear-mail_smtp_password', false);
        $security->assertSee('clear-sms_free_mobile_key', false)->assertDontSee('clear-sms_ntfy_token', false);
    }

    public function test_leaving_a_secret_empty_keeps_it_and_typing_a_value_replaces_it(): void
    {
        $this->settings()->set('mail_smtp_password', self::SECRET);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.settings.mail.update'), ['mail_driver' => 'smtp', 'mail_smtp_host' => 'smtp.example.com', 'mail_smtp_password' => ''])->assertSessionHasNoErrors();
        $this->assertSame(self::SECRET, $this->settings()->get('mail_smtp_password'));
        $this->assertSame('smtp.example.com', $this->settings()->get('mail_smtp_host'));

        $this->actingAs($admin)->post(route('admin.settings.mail.update'), ['mail_driver' => 'smtp', 'mail_smtp_password' => 'nouveau'])->assertSessionHasNoErrors();
        $this->assertSame('nouveau', $this->settings()->get('mail_smtp_password'));
        $this->assertStringStartsWith('enc:v1:', $this->stored('mail_smtp_password'));
    }

    public function test_the_clear_checkbox_removes_a_secret_and_only_a_known_secret_can_be_named(): void
    {
        $this->settings()->set('sms_free_mobile_key', self::SECRET);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.settings.security.update'), ['_sms_save' => '1', 'sms_driver' => 'free_mobile', 'sms_free_mobile_key' => '', 'clear_secrets' => ['mail_smtp_host']])
            ->assertSessionHasErrors('clear_secrets.0');
        $this->assertSame(self::SECRET, $this->settings()->get('sms_free_mobile_key'));

        $this->actingAs($admin)->post(route('admin.settings.security.update'), ['_sms_save' => '1', 'sms_driver' => 'free_mobile', 'sms_free_mobile_key' => '', 'clear_secrets' => ['sms_free_mobile_key']])
            ->assertSessionHasNoErrors();
        $this->assertSame('', $this->settings()->get('sms_free_mobile_key'));
        $this->assertFalse($this->settings()->secretsStatus()['sms_free_mobile_key']);
    }
}
