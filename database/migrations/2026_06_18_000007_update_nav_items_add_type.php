<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nav_items', function (Blueprint $table) {
            $table->enum('type', ['external', 'home', 'reservations', 'my_reservations', 'profile'])
                  ->default('external')
                  ->after('label');
            $table->boolean('admin_only')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('nav_items', function (Blueprint $table) {
            $table->dropColumn(['type', 'admin_only']);
        });
    }
};
