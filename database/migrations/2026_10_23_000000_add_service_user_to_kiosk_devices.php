<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A kiosk device authenticates its own API requests via a per-device service-account User
 * (Auth::onceUsingId(), never a persisted session login — see App\Http\Middleware\
 * AuthenticateKioskDeviceSession) so every existing order/donation-creation controller method
 * works completely unmodified, reading Auth::user() exactly as it already does for a human
 * POS operator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kiosk_devices', function (Blueprint $table) {
            $table->unsignedBigInteger('service_user_id')->nullable()->after('revoked_at');
            $table->foreign('service_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kiosk_devices', function (Blueprint $table) {
            $table->dropForeign(['service_user_id']);
            $table->dropColumn('service_user_id');
        });
    }
};
