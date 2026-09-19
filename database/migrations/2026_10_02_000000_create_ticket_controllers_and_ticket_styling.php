<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Ticket Controller" — a new role (see RolePermission::roles()) granting access to the
 * Ticket module, with the same view/entry/admin tiers Event Coordinator already uses (see
 * EventCoordinatorLevel) but NOT scoped to anything (tickets are a standalone module, not
 * per-event) — so this is a flat "one row per user" grant, unlike event_coordinators which
 * needs one row per (user, event) pair. view/entry both land directly on the Ticket POS;
 * only admin reaches the full Ticket Console (see TicketController::manageTickets()).
 *
 * Also adds optional background_color/image styling to the ticket catalog, so each tile on
 * the kiosk's item grid can be visually distinguished.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_controllers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('level', 20)->default('admin');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('background_color', 20)->nullable()->after('price');
            $table->string('image')->nullable()->after('background_color');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['background_color', 'image']);
        });
        Schema::dropIfExists('ticket_controllers');
    }
};
