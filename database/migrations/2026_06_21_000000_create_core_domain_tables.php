<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile', 15)->nullable()->unique()->after('email');
            $table->string('role', 30)->default('Devotee')->after('password');
            $table->string('status', 30)->default('Active')->after('role');
            $table->boolean('must_change_password')->default(false)->after('status');
            $table->string('registration_otp_hash')->nullable();
            $table->timestamp('registration_otp_expires_at')->nullable();
            $table->unsignedTinyInteger('registration_otp_attempts')->default(0);
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id('membership_id');
            $table->string('membership_name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedTinyInteger('discount_percentage')->default(0);
            $table->date('duration_start')->nullable();
            $table->date('duration_end')->nullable();
            $table->string('status', 30)->default('Active');
            $table->timestamps();
        });

        Schema::create('devotees', function (Blueprint $table) {
            $table->id('devotee_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('membership_id')->nullable();
            $table->date('membership_start_date')->nullable();
            $table->date('membership_end_date')->nullable();
            $table->text('address')->nullable();
            $table->string('gothra')->nullable();
            $table->string('nakshatra')->nullable();
            $table->string('gender', 20)->nullable();
            $table->date('dob')->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamps();
            $table->foreign('membership_id')->references('membership_id')->on('memberships')->nullOnDelete();
        });

        Schema::create('poojas', function (Blueprint $table) {
            $table->id('pooja_id');
            $table->string('pooja_name');
            $table->text('description')->nullable();
            $table->decimal('pooja_fee', 10, 2)->default(0);
            $table->string('duration')->nullable();
            $table->string('status', 30)->default('Active');
            $table->timestamps();
        });

        Schema::create('priests', function (Blueprint $table) {
            $table->string('priest_id', 50)->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('specialization')->nullable();
            $table->unsignedInteger('experience_years')->default(0);
            $table->decimal('monthly_salary', 10, 2)->default(0);
            $table->decimal('wallet_balance', 10, 2)->default(0);
            $table->string('employment_status', 30)->default('Active');
            $table->string('current_status', 30)->default('Offline');
            $table->date('joining_date')->nullable();
            $table->text('address')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('branch_name')->nullable();
            $table->timestamps();
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id('staff_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('salary', 10, 2)->default(0);
            $table->string('employment_status', 30)->default('Active');
            $table->string('current_status', 30)->default('Offline');
            $table->date('joining_date')->nullable();
            $table->string('branch_name')->nullable();
            $table->timestamps();
        });

        Schema::create('trustees', function (Blueprint $table) {
            $table->id('trustee_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('designation')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });

        Schema::create('accountants', function (Blueprint $table) {
            $table->id('accountant_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('salary', 10, 2)->default(0);
            $table->string('employment_status', 30)->default('Active');
            $table->string('current_status', 30)->default('Offline');
            $table->date('joining_date')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('branch_name')->nullable();
            $table->timestamps();
        });

        Schema::create('pooja_bookings', function (Blueprint $table) {
            $table->id('booking_id');
            $table->unsignedBigInteger('devotee_id');
            $table->unsignedBigInteger('pooja_id');
            $table->string('priest_id', 50)->nullable();
            $table->date('booking_date');
            $table->time('booking_time');
            $table->decimal('amount', 10, 2)->default(0);
            $table->enum('payment_status', ['Pending', 'Paid', 'Failed'])->default('Pending');
            $table->enum('booking_status', ['Pending', 'Confirmed', 'Completed', 'Cancelled'])->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->foreign('devotee_id')->references('devotee_id')->on('devotees')->cascadeOnDelete();
            $table->foreign('pooja_id')->references('pooja_id')->on('poojas')->cascadeOnDelete();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id('event_id');
            $table->string('event_name');
            $table->text('description')->nullable();
            $table->date('event_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location')->nullable();
            $table->string('status', 30)->default('Upcoming');
            $table->timestamps();
        });

        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('devotee_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('purpose')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('Paid');
            $table->string('transaction_id')->nullable();
            $table->date('donation_date');
            $table->timestamps();
            $table->foreign('devotee_id')->references('devotee_id')->on('devotees')->nullOnDelete();
        });

        Schema::create('salary_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('salary_month', 7);
            $table->decimal('base_salary', 10, 2)->default(0);
            $table->decimal('total_paid', 10, 2)->default(0);
            $table->string('payment_status', 30)->default('Pending');
            $table->date('payment_date')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->string('priest_id', 50);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason')->nullable();
            $table->string('status', 30)->default('Pending');
            $table->text('admin_remarks')->nullable();
            $table->timestamps();
            $table->foreign('priest_id')->references('priest_id')->on('priests')->cascadeOnDelete();
        });

        Schema::create('priest_wallet_transactions', function (Blueprint $table) {
            $table->id('transaction_id');
            $table->string('priest_id', 50);
            $table->decimal('amount', 10, 2);
            $table->enum('transaction_type', ['Credit', 'Debit'])->default('Credit');
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->foreign('priest_id')->references('priest_id')->on('priests')->cascadeOnDelete();
        });

        Schema::create('priest_status_logs', function (Blueprint $table) {
            $table->id();
            $table->string('priest_id', 50);
            $table->string('status');
            $table->timestamp('changed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('action');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false)->index();
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach ([
            'notifications',
            'audit_logs',
            'priest_status_logs',
            'priest_wallet_transactions',
            'leave_requests',
            'salary_payouts',
            'donations',
            'events',
            'pooja_bookings',
            'accountants',
            'trustees',
            'staff',
            'priests',
            'poojas',
            'devotees',
            'memberships',
        ] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'mobile',
                'role',
                'status',
                'must_change_password',
                'registration_otp_hash',
                'registration_otp_expires_at',
                'registration_otp_attempts',
            ]);
        });
        Schema::enableForeignKeyConstraints();
    }
};
