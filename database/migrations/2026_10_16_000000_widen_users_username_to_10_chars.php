<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kiosk usernames were originally capped at 6 characters (varchar(6)) — widened to allow
     * 6-10, giving admins room for more memorable/distinct names across many counters.
     *
     * Raw SQL on MySQL (matches change_priest_id_to_varchar_in_tables's own convention) —
     * Schema::table(...)->change() needs doctrine/dbal there, which this project doesn't
     * otherwise depend on; the test suite's sqlite connection takes the Blueprint path
     * instead, where ->change() works natively.
     */
    public function up(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY username VARCHAR(10) NULL');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('username', 10)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY username VARCHAR(6) NULL');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('username', 6)->nullable()->change();
            });
        }
    }
};
