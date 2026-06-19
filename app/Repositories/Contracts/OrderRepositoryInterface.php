<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use Illuminate\Support\Collection;

interface OrderRepositoryInterface
{
    /**
     * Create a new order.
     */
    public function create(array $data): Order;

    /**
     * Create an order item.
     */
    public function createItem(array $data): OrderItem;

    /**
     * Create an order item allocation.
     */
    public function createAllocation(array $data): OrderItemAllocation;

    /**
     * Find an order or fail.
     */
    public function findOrFail(int $id): Order;

    /**
     * Find an order item by order ID and product ID.
     */
    public function findOrderItem(int $orderId, int $productId): ?OrderItem;

    /**
     * Get allocations for a specific order item.
     */
    public function getOrderItemAllocations(int $orderItemId): Collection;
}
