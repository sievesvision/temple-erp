<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('event_name');
        });

        // Backfill a stable slug (name + date, no id) for any existing events,
        // guarding against collisions between rows sharing the same name+date.
        $usedSlugs = [];
        foreach (DB::table('events')->orderBy('event_id')->get() as $event) {
            $datePart = $event->event_date ? date('Y-m-d', strtotime($event->event_date)) : '';
            $base = Str::slug(trim($event->event_name . ' ' . $datePart));
            $slug = $base;
            $suffix = 2;
            while (in_array($slug, $usedSlugs, true)) {
                $slug = $base . '-' . $suffix;
                $suffix++;
            }
            $usedSlugs[] = $slug;

            DB::table('events')->where('event_id', $event->event_id)->update(['slug' => $slug]);
        }

        Schema::table('events', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
