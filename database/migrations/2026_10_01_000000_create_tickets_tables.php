<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A standalone ticket-selling module (not tied to any Event) — a temple-wide catalog of
 * sellable ticket types (e.g. "Adult Entry $10", "Prasadam Coupon $5"), sold through their
 * own dedicated kiosk page. Mirrors the donations module's own shape (a catalog + an "order"
 * record + line items, the same way Event -> EventDonationOption -> donation_selections
 * works) but stays entirely separate from it, per this being a standalone module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('status', 20)->default('Active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ticket_orders', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->string('payment_method', 30);
            $table->string('payment_status', 20)->default('Paid');
            $table->string('transaction_id')->nullable();
            $table->date('order_date');
            $table->unsignedBigInteger('sold_by')->nullable();
            $table->timestamps();

            $table->foreign('sold_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('ticket_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_order_id');
            $table->unsignedBigInteger('ticket_id')->nullable();
            // Snapshotted at sale time so a later price/name change on the Ticket catalog
            // entry never rewrites what a past order actually charged/sold.
            $table->string('ticket_name');
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 10, 2);
            $table->timestamps();

            $table->foreign('ticket_order_id')->references('id')->on('ticket_orders')->cascadeOnDelete();
            $table->foreign('ticket_id')->references('id')->on('tickets')->nullOnDelete();
        });

        // One row per physical stub to print — ordering 5 of a $5 ticket produces 5 of these,
        // each with its own sequential, unique stub number so every printed piece of paper is
        // individually identifiable (and reprintable on its own later).
        Schema::create('ticket_stubs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_order_item_id');
            // 40 chars: comfortably fits the final "TKT000123" form as well as the temporary
            // UUID placeholder TicketStub::createForItem() briefly assigns before the row's
            // own id is known (see that method).
            $table->string('stub_number', 40)->unique();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->foreign('ticket_order_item_id')->references('id')->on('ticket_order_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_stubs');
        Schema::dropIfExists('ticket_order_items');
        Schema::dropIfExists('ticket_orders');
        Schema::dropIfExists('tickets');
    }
};
