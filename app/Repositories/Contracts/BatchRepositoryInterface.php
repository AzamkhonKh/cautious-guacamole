<?php

namespace App\Repositories\Contracts;

use App\Models\Batch;
use App\Models\BatchProduct;
use Illuminate\Support\Collection;

interface BatchRepositoryInterface
{
    /**
     * Create a new batch.
     */
    public function create(array $data): Batch;

    /**
     * Create a batch product record.
     */
    public function createProduct(array $data): BatchProduct;

    /**
     * Find a batch or fail.
     */
    public function findOrFail(int $id): Batch;

    /**
     * Lock the batch products for updating to prevent race conditions.
     */
    public function lockBatchProducts(int $batchId): Collection;

    /**
     * Lock batch products of given product IDs for update.
     */
    public function lockProductsForUpdate(array $productIds): void;

    /**
     * Fetch available batch products (remaining_qty > 0) ordered by FIFO in a specific storage.
     */
    public function getAvailableBatchesForProduct(int $productId, int $storageId): Collection;

    /**
     * Decrement remaining quantity of a batch product.
     */
    public function decrementRemainingQuantity(int $batchProductId, int $qty): void;

    /**
     * Increment remaining quantity of a batch product.
     */
    public function incrementRemainingQuantity(int $batchProductId, int $qty): void;

    /**
     * Find or create a batch product in a specific storage for refunds.
     */
    public function findOrCreateRefundBatchProduct(int $productId, int $storageId, float $purchasePrice): BatchProduct;
}
