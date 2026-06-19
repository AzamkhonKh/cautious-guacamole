<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->foreignId('storage_id')->constrained('storages')->cascadeOnDelete();
            $table->dateTime('purchase_date');
            $table->timestamps();
        });

        Schema::create('batch_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity');
            $table->decimal('purchase_price', 10, 2);
            $table->integer('remaining_quantity');
            $table->timestamps();

            $table->unique(['batch_id', 'product_id']);
        });

        Schema::create('provider_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->dateTime('refund_date');
            $table->timestamps();
        });

        Schema::create('provider_refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_refund_id')->constrained('provider_refunds')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity');
            $table->timestamps();

            $table->unique(['provider_refund_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_refund_items');
        Schema::dropIfExists('provider_refunds');
        Schema::dropIfExists('batch_products');
        Schema::dropIfExists('batches');
    }
};
