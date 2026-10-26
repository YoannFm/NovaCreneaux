<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Clear placeholder icon — was set as column default, not intentional
        DB::table('roles')->where('icon', 'bi bi-star')->update(['icon' => '']);

        // Only the lowest-power non-admin role should be locked (default role for new users)
        // The admin role can be deleted if needed
        DB::table('roles')->where('is_admin', true)->update(['locked' => false]);

        // Change column default to empty string
        Schema::table('roles', function (Blueprint $table) {
            $table->string('icon', 60)->default('')->change();
        });
    }

    public function down(): void {
        DB::table('roles')->where('icon', '')->update(['icon' => 'bi bi-star']);
        DB::table('roles')->where('is_admin', true)->update(['locked' => true]);
        Schema::table('roles', function (Blueprint $table) {
            $table->string('icon', 60)->default('bi bi-star')->change();
        });
    }
};
