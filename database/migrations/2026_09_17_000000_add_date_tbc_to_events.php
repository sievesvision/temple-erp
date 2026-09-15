<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A display-only override: event_date stays required (still used for sorting and by
     * every internal admin screen/dropdown as a working date), but when date_tbc is set the
     * public-facing pages (homepage event card, event donation page) show "Date to be
     * confirmed" instead of that working date — for events like Kumbabishekam 2027 where a
     * placeholder date is needed internally long before the real date is fixed.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'date_tbc')) {
                $table->boolean('date_tbc')->default(false)->after('event_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('date_tbc');
        });
    }
};
