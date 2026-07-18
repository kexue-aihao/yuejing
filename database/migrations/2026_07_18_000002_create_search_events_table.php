<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('novel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('query', 160)->nullable();
            $table->string('locale', 32)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('session_hash', 64)->nullable()->index();
            $table->timestamps();
            $table->index(['user_id', 'category_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_events');
    }
};
