<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->string('preset', 40)->nullable()->after('format');
            $table->text('settings')->nullable()->after('preset');
        });

        // Discord etait un format a part : c'est desormais un prereglage du catalogue
        DB::table('webhooks')->where('format', 'discord')->update(['format' => 'preset', 'preset' => 'discord']);
    }

    public function down(): void
    {
        DB::table('webhooks')->where('format', 'preset')->where('preset', 'discord')->update(['format' => 'discord']);
        DB::table('webhooks')->whereIn('format', ['preset', 'custom'])->update(['format' => 'json']);

        Schema::table('webhooks', function (Blueprint $table) {
            $table->dropColumn(['preset', 'settings']);
        });
    }
};
