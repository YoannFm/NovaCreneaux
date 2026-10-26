<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->time('open_time')->default('17:00:00');
            $table->time('close_time')->default('11:30:00');
            $table->json('closed_days')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('resources'); }
};
