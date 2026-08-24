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
        if (!Schema::hasTable('inventories')) {
            Schema::create('inventories', function (Blueprint $table) {
                $table->id('item_id');
                $table->string('item_name');
                $table->string('category');
                $table->decimal('quantity', 10, 2)->default(0.00);
                $table->string('unit');
                $table->decimal('minimum_threshold', 10, 2)->default(10.00);
                $table->date('last_restocked')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('inventory_transactions')) {
            Schema::create('inventory_transactions', function (Blueprint $table) {
                $table->id('transaction_id');
                $table->unsignedBigInteger('item_id');
                $table->enum('transaction_type', ['Restock', 'Consume', 'Adjustment']);
                $table->decimal('quantity', 10, 2);
                $table->string('remarks')->nullable();
                $table->date('transaction_date');
                $table->timestamps();

                $table->foreign('item_id')->references('item_id')->on('inventories')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventories');
    }
};
