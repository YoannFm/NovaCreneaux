<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('message')->nullable();
            $table->enum('type', ['info', 'success', 'warning', 'danger'])->default('info');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('user_notifications'); }
};
