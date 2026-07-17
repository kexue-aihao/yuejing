<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('private_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_low_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_high_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_low_id', 'user_high_id']);
            $table->index('user_high_id');
        });

        Schema::create('private_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('private_conversation_id')
                ->constrained('private_conversations')
                ->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['private_conversation_id', 'created_at']);
            $table->index('sender_id');
        });

        Schema::create('chat_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('creator_id');
        });

        Schema::create('chat_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_group_id')->constrained('chat_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['chat_group_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('chat_group_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_group_id')->constrained('chat_groups')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['chat_group_id', 'created_at']);
            $table->index('sender_id');
        });

        Schema::create('chat_group_message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_group_message_id')
                ->constrained('chat_group_messages')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['chat_group_message_id', 'user_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_group_message_reads');
        Schema::dropIfExists('chat_group_messages');
        Schema::dropIfExists('chat_group_members');
        Schema::dropIfExists('chat_groups');
        Schema::dropIfExists('private_messages');
        Schema::dropIfExists('private_conversations');
    }
};
