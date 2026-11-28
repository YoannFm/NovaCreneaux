<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->string('format', 16)->default('json')->after('events');
            $table->boolean('show_names')->default(false)->after('format');
        });
    }

    public function down(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->dropColumn(['format', 'show_names']);
        });
    }
};
