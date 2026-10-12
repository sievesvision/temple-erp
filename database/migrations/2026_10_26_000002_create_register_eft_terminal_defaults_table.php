<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The same physical register can serve different events (and ticket sales) over its
 * lifetime, each wanting its own default EFT terminal — one row per (register, context)
 * pair. context_type is 'event' (context_id = events.event_id) or 'tickets'
 * (context_id null). See EftTerminal::defaultForContext() for the resolution priority
 * this feeds into: register's configured default for THIS context, then the existing
 * fallback chain (user's preferred terminal, registry is_default, first paired).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('register_eft_terminal_defaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('register_id')->constrained()->cascadeOnDelete();
            $table->string('context_type', 10); // 'event' | 'tickets'
            $table->unsignedBigInteger('context_id')->nullable(); // events.event_id, or null for 'tickets'
            $table->foreignId('eft_terminal_id')->constrained('eft_terminals')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['register_id', 'context_type', 'context_id'], 'register_context_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('register_eft_terminal_defaults');
    }
};
