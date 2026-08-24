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
        // 1. Update poojas table
        Schema::table('poojas', function (Blueprint $table) {
            if (!Schema::hasColumn('poojas', 'online_allowed')) {
                $table->boolean('online_allowed')->default(true)->after('description');
            }
            if (!Schema::hasColumn('poojas', 'category')) {
                $table->string('category', 50)->default('General')->after('online_allowed');
            }
        });

        // 2. Update pooja_bookings table
        Schema::table('pooja_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('pooja_bookings', 'booking_type')) {
                $table->enum('booking_type', ['Offline', 'Online'])->default('Offline')->after('booking_time');
            }
            if (!Schema::hasColumn('pooja_bookings', 'delivery_address')) {
                $table->text('delivery_address')->nullable()->after('booking_type');
            }
            if (!Schema::hasColumn('pooja_bookings', 'shipping_charge')) {
                $table->decimal('shipping_charge', 10, 2)->default(0.00)->after('delivery_address');
            }
            if (!Schema::hasColumn('pooja_bookings', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->default(0.00)->after('amount');
            }
            if (!Schema::hasColumn('pooja_bookings', 'total_amount')) {
                $table->decimal('total_amount', 10, 2)->default(0.00)->after('discount_amount');
            }
            if (!Schema::hasColumn('pooja_bookings', 'payment_method')) {
                $table->enum('payment_method', ['UPI', 'Razorpay', 'Cash', 'Counter'])->nullable()->after('total_amount');
            }
        });

        // Alter payment_status column in pooja_bookings using raw query to support 'Refunded'
        DB::statement("ALTER TABLE pooja_bookings MODIFY COLUMN payment_status ENUM('Pending', 'Paid', 'Failed', 'Refunded') NULL");

        // 3. Create booking_payments table
        if (!Schema::hasTable('booking_payments')) {
            Schema::create('booking_payments', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('booking_id')->unsigned();
                $table->string('payment_method', 50);
                $table->string('transaction_id', 100)->nullable();
                $table->decimal('amount', 10, 2);
                $table->enum('status', ['Pending', 'Paid', 'Failed', 'Refunded'])->default('Pending');
                $table->timestamps();
            });
        }

        // 4. Create booking_status_logs table
        if (!Schema::hasTable('booking_status_logs')) {
            Schema::create('booking_status_logs', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('booking_id')->unsigned();
                $table->string('status_from', 50)->nullable();
                $table->string('status_to', 50);
                $table->bigInteger('changed_by')->nullable()->unsigned();
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_status_logs');
        Schema::dropIfExists('booking_payments');

        Schema::table('pooja_bookings', function (Blueprint $table) {
            $table->dropColumn([
                'booking_type',
                'delivery_address',
                'shipping_charge',
                'discount_amount',
                'total_amount',
                'payment_method'
            ]);
        });

        DB::statement("ALTER TABLE pooja_bookings MODIFY COLUMN payment_status ENUM('Pending', 'Paid', 'Failed') NULL");

        Schema::table('poojas', function (Blueprint $table) {
            $table->dropColumn(['online_allowed', 'category']);
        });
    }
};
