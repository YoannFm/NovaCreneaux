<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('installation')) Schema::create('installation', function (Blueprint $table) {
            $table->id();
            $table->boolean('completed')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('installation'); }
};
