<?php

namespace Tests\Feature;

use App\Services\QrCode;
use App\Services\SettingService;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Le QR code de double authentification contient la cle secrete : il est dessine par le serveur, jamais par un service externe. */
class MfaQrCodeTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    public function test_the_qr_code_is_an_inline_svg_that_does_not_carry_the_content_as_text(): void
    {
        $svg = app(QrCode::class)->svg('otpauth://totp/Site:lea?secret=ABCDEFGHIJKLMNOP', 120);

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringNotContainsString('ABCDEFGHIJKLMNOP', $svg);
        $this->assertStringNotContainsString('<?xml', $svg);
    }

    public function test_the_authenticator_setup_page_draws_the_code_of_the_account_secret_locally(): void
    {
        app(SettingService::class)->set('app_name', 'Ma Médiathèque');
        $user = $this->student();

        // Ajouter une methode demande le mot de passe : il vient d'etre confirme
        $page = $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get(route('mfa.totp.enable.form'))->assertOk();

        $secret = session('mfa_totp_setup_secret');
        $expected = app(QrCode::class)->svg(app(TotpService::class)->getOtpauthUrl($user->email, $secret, 'Ma Médiathèque'));
        $page->assertSee($expected, false)->assertDontSee('qrserver', false)->assertDontSee('otpauth://', false);
    }

    public function test_the_sms_setup_page_calls_no_external_qr_service(): void
    {
        app(SettingService::class)->set('sms_driver', 'ntfy');

        $this->actingAs($this->student())->withSession(['auth.password_confirmed_at' => time()])->get(route('mfa.sms.setup'))->assertOk()
            ->assertSee('<svg', false)->assertDontSee('qrserver', false);
    }
}
