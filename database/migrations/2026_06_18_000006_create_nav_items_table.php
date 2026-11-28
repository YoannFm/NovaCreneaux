<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nav_items')) Schema::create('nav_items', function (Blueprint $table) {
            $table->id();
            $table->string('label', 60);
            $table->string('url', 500);
            $table->string('icon', 60)->default('bi-link-45deg');
            $table->enum('target', ['_self', '_blank'])->default('_self');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nav_items');
    }
};
