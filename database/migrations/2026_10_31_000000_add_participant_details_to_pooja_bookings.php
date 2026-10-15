<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pooja_bookings', function (Blueprint $table) {
            // Free-text, not structured per-participant fields — a booking commonly covers
            // several people (a whole family), each with their own gothram/nakshatram, and
            // the counter/devotee just writes it out rather than filling in a repeating
            // form. Shown to the priest/staff fulfilling the booking, not validated.
            $table->text('participant_details')->nullable()->after('custom_pooja_label');
        });
    }

    public function down(): void
    {
        Schema::table('pooja_bookings', function (Blueprint $table) {
            $table->dropColumn('participant_details');
        });
    }
};
