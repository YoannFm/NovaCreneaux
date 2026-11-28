<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->integer('max_reservations_day')->default(-1)->after('locked');
            $table->integer('max_reservations_week')->default(-1)->after('max_reservations_day');
            $table->integer('cancel_deadline_minutes')->default(-1)->after('max_reservations_week');
            $table->integer('advance_days')->default(30)->after('cancel_deadline_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['max_reservations_day', 'max_reservations_week', 'cancel_deadline_minutes', 'advance_days']);
        });
    }
};
