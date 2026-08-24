<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE priests MODIFY COLUMN priest_id VARCHAR(50) NOT NULL");
            DB::statement("ALTER TABLE pooja_bookings MODIFY COLUMN priest_id VARCHAR(50) NULL");
            DB::statement("ALTER TABLE leave_requests MODIFY COLUMN priest_id VARCHAR(50) NULL");
        } else {
            Schema::table('priests', function (Blueprint $table) {
                $table->string('priest_id', 50)->change();
            });
            Schema::table('pooja_bookings', function (Blueprint $table) {
                $table->string('priest_id', 50)->nullable()->change();
            });
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->string('priest_id', 50)->nullable()->change();
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE priests MODIFY COLUMN priest_id INT NOT NULL");
            DB::statement("ALTER TABLE pooja_bookings MODIFY COLUMN priest_id INT NULL");
            DB::statement("ALTER TABLE leave_requests MODIFY COLUMN priest_id INT NULL");
        } else {
            Schema::table('priests', function (Blueprint $table) {
                $table->integer('priest_id')->change();
            });
            Schema::table('pooja_bookings', function (Blueprint $table) {
                $table->integer('priest_id')->nullable()->change();
            });
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->integer('priest_id')->nullable()->change();
            });
        }

        Schema::enableForeignKeyConstraints();
    }
};
