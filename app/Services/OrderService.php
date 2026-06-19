<?php

namespace App\Services;

use App\Models\ClientRefund;
use App\Models\Order;
use App\Repositories\Contracts\BatchRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;
use Illuminate\Support\Facades\DB;

class OrderService
{
    protected OrderRepositoryInterface $orderRepository;
    protected BatchRepositoryInterface $batchRepository;
    protected ProductRepositoryInterface $productRepository;
    protected RefundRepositoryInterface $refundRepository;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        BatchRepositoryInterface $batchRepository,
        ProductRepositoryInterface $productRepository,
        RefundRepositoryInterface $refundRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->batchRepository = $batchRepository;
        $this->productRepository = $productRepository;
        $this->refundRepository = $refundRepository;
    }

    /**
     * Create Client Orders (FIFO Stock Allocation).
     */
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $clientId = $data['client_id'];
            $storageId = $data['storage_id'];
            $orderDate = $data['order_date'] ?? now();
            $products = $data['products'];

            // Get product IDs to lock
            $productIds = collect($products)->pluck('id')->all();

            // Lock the matching batch products to avoid race conditions
            $this->batchRepository->lockProductsForUpdate($productIds);

            $order = $this->orderRepository->create([
                'client_id' => $clientId,
                'storage_id' => $storageId,
                'order_date' => $orderDate,
            ]);

            foreach ($products as $orderItemData) {
                $productId = $orderItemData['id'];
                $qtyOrdered = $orderItemData['qty'];

                $product = $this->productRepository->findOrFail($productId);

                // Fetch available batch products (FIFO: ordered by batch purchase_date ASC) in the chosen storage
                $batches = $this->batchRepository->getAvailableBatchesForProduct($productId, $storageId);

                $totalAvailable = $batches->sum('remaining_quantity');

                if ($totalAvailable < $qtyOrdered) {
                    throw new \InvalidArgumentException(
                        "Insufficient stock for product '{$product->name}'. Ordered: {$qtyOrdered}, Available: {$totalAvailable}."
                    );
                }

                // Check that client selling price is higher than provider purchase price for the batches we will use
                $remainingToCheck = $qtyOrdered;
                foreach ($batches as $batchProduct) {
                    if ($remainingToCheck <= 0) {
                        break;
                    }
                    $checkQty = min($batchProduct->remaining_quantity, $remainingToCheck);
                    
                    $purchasePrice = round((float) $batchProduct->purchase_price, 2);
                    $sellingPrice = round((float) $product->price, 2);

                    if ($sellingPrice <= $purchasePrice) {
                        throw new \InvalidArgumentException(
                            "The selling price of product '{$product->name}' must be higher than the purchase price from the provider."
                        );
                    }
                    
                    $remainingToCheck -= $checkQty;
                }

                // Create order item using product's current price
                $orderItem = $this->orderRepository->createItem([
                    'order_id' => $order->id,
                    'product_id' => $productId,
                    'quantity' => $qtyOrdered,
                    'price' => $product->price,
                ]);

                // Allocate stock using FIFO
                $remainingToAllocate = $qtyOrdered;
                foreach ($batches as $batchProduct) {
                    if ($remainingToAllocate <= 0) {
                        break;
                    }

                    $allocateQty = min($batchProduct->remaining_quantity, $remainingToAllocate);

                    // Decrement remaining quantity
                    $this->batchRepository->decrementRemainingQuantity($batchProduct->id, $allocateQty);

                    $this->orderRepository->createAllocation([
                        'order_item_id' => $orderItem->id,
                        'batch_product_id' => $batchProduct->id,
                        'quantity' => $allocateQty,
                    ]);

                    $remainingToAllocate -= $allocateQty;
                }
            }

            return $order->load('items.allocations');
        });
    }

    /**
     * Refund client orders (returns back to storages and batches).
     */
    public function refundFromClient(array $data): ClientRefund
    {
        return DB::transaction(function () use ($data) {
            $orderId = $data['order_id'];
            $refundDate = $data['refund_date'] ?? now();
            $products = $data['products'];

            $order = $this->orderRepository->findOrFail($orderId);
            $targetStorageId = $data['storage_id'] ?? $order->storage_id;

            $clientRefund = $this->refundRepository->createClientRefund([
                'order_id' => $order->id,
                'refund_date' => $refundDate,
            ]);

            foreach ($products as $refundItemData) {
                $productId = $refundItemData['id'];
                $qtyToRefund = $refundItemData['qty'];

                $orderItem = $this->orderRepository->findOrderItem($orderId, $productId);

                if (! $orderItem) {
                    throw new \InvalidArgumentException("Product ID {$productId} was not purchased in Order {$orderId}.");
                }

                // Check allocations to see what can be refunded
                $allocations = $this->orderRepository->getOrderItemAllocations($orderItem->id);

                // Compute already refunded quantities for each allocation
                $refundedQuantities = $this->refundRepository->getClientRefundedQuantities($allocations->pluck('id'));

                $totalNetSold = 0;
                $allocationNetSold = [];

                foreach ($allocations as $alloc) {
                    $refunded = $refundedQuantities->has($alloc->id)
                        ? (int) $refundedQuantities->get($alloc->id)->total_refunded
                        : 0;

                    $netSold = $alloc->quantity - $refunded;
                    $totalNetSold += $netSold;
                    $allocationNetSold[] = [
                        'allocation' => $alloc,
                        'net_sold' => $netSold,
                    ];
                }

                if ($totalNetSold < $qtyToRefund) {
                    throw new \InvalidArgumentException(
                        "Cannot refund {$qtyToRefund} units of product ID {$productId}. Max returnable is {$totalNetSold}."
                    );
                }

                $clientRefundItem = $this->refundRepository->createClientRefundItem([
                    'client_refund_id' => $clientRefund->id,
                    'product_id' => $productId,
                    'quantity' => $qtyToRefund,
                ]);

                // Revert allocations starting from newest allocated batch or standard iteration
                $remainingToRefund = $qtyToRefund;
                foreach ($allocationNetSold as $item) {
                    if ($remainingToRefund <= 0) {
                        break;
                    }

                    $alloc = $item['allocation'];
                    $netSold = $item['net_sold'];

                    if ($netSold <= 0) {
                        continue;
                    }

                    $refundQty = min($netSold, $remainingToRefund);

                    // Create client refund allocation
                    $this->refundRepository->createClientRefundAllocation([
                        'client_refund_item_id' => $clientRefundItem->id,
                        'order_item_allocation_id' => $alloc->id,
                        'quantity' => $refundQty,
                    ]);

                    // Put stock back in batch remaining_quantity of the active storage
                    $originalBatchProduct = \App\Models\BatchProduct::findOrFail($alloc->batch_product_id);
                    $targetBatchProduct = $this->batchRepository->findOrCreateRefundBatchProduct(
                        $productId,
                        $targetStorageId,
                        (float) $originalBatchProduct->purchase_price
                    );
                    $this->batchRepository->incrementRemainingQuantity($targetBatchProduct->id, $refundQty);

                    $remainingToRefund -= $refundQty;
                }
            }

            return $clientRefund->load('items.allocations');
        });
    }
}
