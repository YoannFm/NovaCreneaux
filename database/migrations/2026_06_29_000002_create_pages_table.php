<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('slug')->unique();
            $table->longText('content');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_restricted')->default(false);
            $table->timestamps();
        });

        Schema::create('page_role', function (Blueprint $table) {
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['page_id', 'role_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('page_role');
        Schema::dropIfExists('pages');
    }
};
