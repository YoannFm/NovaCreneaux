<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['warning', 'ban']);
            $table->text('reason');
            $table->date('banned_until')->nullable(); // null = permanent ban
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctions');
    }
};
