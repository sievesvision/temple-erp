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
        Schema::table('events', function (Blueprint $table) {
            $table->string('header_image')->nullable()->after('location');
            $table->string('flyer_image')->nullable()->after('header_image');
            $table->boolean('show_donation_summary')->default(true)->after('flyer_image');
        });

        Schema::create('event_donation_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('label');
            $table->decimal('amount', 10, 2)->nullable();
            $table->boolean('allow_quantity')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('event_id')->references('event_id')->on('events')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_donation_options');

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['header_image', 'flyer_image', 'show_donation_summary']);
        });
    }
};
