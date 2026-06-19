<?php

namespace App\Repositories\Eloquent;

use App\Models\Batch;
use App\Models\BatchProduct;
use App\Repositories\Contracts\BatchRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentBatchRepository implements BatchRepositoryInterface
{
    /**
     * Create a new batch.
     */
    public function create(array $data): Batch
    {
        return Batch::create($data);
    }

    /**
     * Create a batch product record.
     */
    public function createProduct(array $data): BatchProduct
    {
        return BatchProduct::create($data);
    }

    /**
     * Find a batch or fail.
     */
    public function findOrFail(int $id): Batch
    {
        return Batch::findOrFail($id);
    }

    /**
     * Lock the batch products for updating to prevent race conditions.
     */
    public function lockBatchProducts(int $batchId): Collection
    {
        return BatchProduct::where('batch_id', $batchId)
            ->lockForUpdate()
            ->get()
            ->keyBy('product_id');
    }

    /**
     * Lock batch products of given product IDs for update.
     */
    public function lockProductsForUpdate(array $productIds): void
    {
        BatchProduct::whereIn('product_id', $productIds)
            ->lockForUpdate()
            ->get();
    }

    /**
     * Fetch available batch products (remaining_qty > 0) ordered by FIFO in a specific storage.
     */
    public function getAvailableBatchesForProduct(int $productId, int $storageId): Collection
    {
        return BatchProduct::join('batches', 'batch_products.batch_id', '=', 'batches.id')
            ->where('batch_products.product_id', $productId)
            ->where('batches.storage_id', $storageId)
            ->where('batch_products.remaining_quantity', '>', 0)
            ->orderBy('batches.purchase_date', 'asc')
            ->orderBy('batches.created_at', 'asc')
            ->select('batch_products.*')
            ->get();
    }

    /**
     * Decrement remaining quantity of a batch product.
     */
    public function decrementRemainingQuantity(int $batchProductId, int $qty): void
    {
        $batchProduct = BatchProduct::findOrFail($batchProductId);
        $batchProduct->decrement('remaining_quantity', $qty);
    }

    /**
     * Increment remaining quantity of a batch product.
     */
    public function incrementRemainingQuantity(int $batchProductId, int $qty): void
    {
        $batchProduct = BatchProduct::findOrFail($batchProductId);
        $batchProduct->increment('remaining_quantity', $qty);
    }

    /**
     * Find or create a batch product in a specific storage for refunds.
     */
    public function findOrCreateRefundBatchProduct(int $productId, int $storageId, float $purchasePrice): BatchProduct
    {
        $batchProduct = BatchProduct::join('batches', 'batch_products.batch_id', '=', 'batches.id')
            ->where('batches.storage_id', $storageId)
            ->where('batch_products.product_id', $productId)
            ->orderBy('batches.purchase_date', 'desc')
            ->select('batch_products.*')
            ->first();

        if ($batchProduct) {
            return $batchProduct;
        }

        // Traverse category hierarchy to find provider_id
        $product = \App\Models\Product::with('category.parent')->findOrFail($productId);
        $providerId = null;
        if ($product->category) {
            $providerId = $product->category->provider_id;
            if (!$providerId && $product->category->parent) {
                $providerId = $product->category->parent->provider_id;
            }
        }
        if (!$providerId) {
            $providerId = \App\Models\Provider::first()?->id;
        }

        $batch = Batch::create([
            'provider_id' => $providerId,
            'storage_id' => $storageId,
            'purchase_date' => now(),
        ]);

        return BatchProduct::create([
            'batch_id' => $batch->id,
            'product_id' => $productId,
            'quantity' => 0,
            'purchase_price' => $purchasePrice,
            'remaining_quantity' => 0,
        ]);
    }
}
