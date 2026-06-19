<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\ProviderRefund;
use App\Repositories\Contracts\BatchRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    protected BatchRepositoryInterface $batchRepository;
    protected RefundRepositoryInterface $refundRepository;
    protected ProductRepositoryInterface $productRepository;

    public function __construct(
        BatchRepositoryInterface $batchRepository,
        RefundRepositoryInterface $refundRepository,
        ProductRepositoryInterface $productRepository
    ) {
        $this->batchRepository = $batchRepository;
        $this->refundRepository = $refundRepository;
        $this->productRepository = $productRepository;
    }

    /**
     * Purchase Products and Add to Storage.
     */
    public function purchase(array $data): Batch
    {
        return DB::transaction(function () use ($data) {
            $purchaseDate = $data['purchase_date'] ?? now();

            $batch = $this->batchRepository->create([
                'provider_id' => $data['provider_id'],
                'storage_id' => $data['storage_id'],
                'purchase_date' => $purchaseDate,
            ]);

            foreach ($data['products'] as $item) {
                $product = $this->productRepository->findOrFail($item['product_id']);

                $purchasePrice = round((float) $item['purchase_price'], 2);
                $sellingPrice = round((float) $product->price, 2);

                if ($sellingPrice <= $purchasePrice) {
                    throw new \InvalidArgumentException(
                        "The purchase price for product '{$product->name}' must be lower than its selling price of {$product->price}."
                    );
                }

                $this->batchRepository->createProduct([
                    'batch_id' => $batch->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['qty'],
                    'purchase_price' => $item['purchase_price'],
                    'remaining_quantity' => $item['qty'],
                ]);
            }

            return $batch->load('batchProducts.product');
        });
    }

    /**
     * Refund Purchased Products on Batches (Back to Provider).
     */
    public function refundToProvider(array $data): ProviderRefund
    {
        return DB::transaction(function () use ($data) {
            $batchId = $data['batch_id'];
            $refundDate = $data['refund_date'] ?? now();

            $batch = $this->batchRepository->findOrFail($batchId);

            // Lock batch products to avoid race conditions
            $batchProducts = $this->batchRepository->lockBatchProducts($batchId);

            $providerRefund = $this->refundRepository->createProviderRefund([
                'batch_id' => $batch->id,
                'refund_date' => $refundDate,
            ]);

            foreach ($data['products'] as $item) {
                $productId = $item['product_id'];
                $qtyToRefund = $item['qty'];

                if (! $batchProducts->has($productId)) {
                    throw new \InvalidArgumentException("Product ID {$productId} is not part of batch {$batchId}.");
                }

                $batchProduct = $batchProducts->get($productId);

                if ($batchProduct->remaining_quantity < $qtyToRefund) {
                    throw new \InvalidArgumentException(
                        "Cannot refund {$qtyToRefund} of product ID {$productId}. Only {$batchProduct->remaining_quantity} units are available in this batch."
                    );
                }

                // Deduct remaining quantity
                $this->batchRepository->decrementRemainingQuantity($batchProduct->id, $qtyToRefund);

                $this->refundRepository->createProviderRefundItem([
                    'provider_refund_id' => $providerRefund->id,
                    'product_id' => $productId,
                    'quantity' => $qtyToRefund,
                ]);
            }

            return $providerRefund->load('items.product');
        });
    }
}
