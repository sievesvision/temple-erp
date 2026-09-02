<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Committee members were previously just a 'users' row with role='Committee' — no dedicated
 * table, unlike every other staff-like role (trustees, accountants, staff, priests all have
 * one alongside 'users'). This adds the same pattern so committee members can carry their
 * own fields, starting with 'position' (e.g. Secretary, Treasurer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committees', function (Blueprint $table) {
            $table->id('committee_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('position')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committees');
    }
};
