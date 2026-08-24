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
        if (!Schema::hasTable('chat_sessions')) {
            Schema::create('chat_sessions', function (Blueprint $table) {
                $table->id('session_id');
                $table->unsignedBigInteger('devotee_id');
                $table->string('status')->default('active'); // active, ended
                $table->string('mode')->default('bot'); // bot, agent
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table) {
                $table->id('message_id');
                $table->unsignedBigInteger('session_id');
                $table->string('sender_type'); // devotee, staff, bot
                $table->unsignedBigInteger('sender_id')->nullable();
                $table->text('message_text');
                $table->text('metadata')->nullable(); // JSON/serialized metadata for temporary state
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');
    }
};
