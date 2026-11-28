<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('mfa_email_enabled')->default(false)->after('two_factor_confirmed_at');
            $table->boolean('mfa_sms_enabled')->default(false)->after('mfa_email_enabled');
            $table->string('mfa_phone', 20)->nullable()->after('mfa_sms_enabled');
            $table->text('mfa_webauthn_credentials')->nullable()->after('mfa_phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mfa_email_enabled', 'mfa_sms_enabled', 'mfa_phone', 'mfa_webauthn_credentials']);
        });
    }
};
