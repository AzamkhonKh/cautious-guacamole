<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('storage_id')->constrained('storages')->cascadeOnDelete();
            $table->dateTime('order_date');
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity');
            $table->decimal('price', 10, 2);
            $table->timestamps();

            $table->unique(['order_id', 'product_id']);
        });

        Schema::create('order_item_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('batch_product_id')->constrained('batch_products')->cascadeOnDelete();
            $table->integer('quantity');
            $table->timestamps();

            $table->unique(['order_item_id', 'batch_product_id']);
        });

        Schema::create('client_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->dateTime('refund_date');
            $table->timestamps();
        });

        Schema::create('client_refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_refund_id')->constrained('client_refunds')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity');
            $table->timestamps();

            $table->unique(['client_refund_id', 'product_id']);
        });

        Schema::create('client_refund_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_refund_item_id')->constrained('client_refund_items')->cascadeOnDelete();
            $table->foreignId('order_item_allocation_id')->constrained('order_item_allocations')->cascadeOnDelete();
            $table->integer('quantity');
            $table->timestamps();

            $table->unique(['client_refund_item_id', 'order_item_allocation_id'], 'cr_alloc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_refund_allocations');
        Schema::dropIfExists('client_refund_items');
        Schema::dropIfExists('client_refunds');
        Schema::dropIfExists('order_item_allocations');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
