<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    /**
     * Create a new order.
     */
    public function create(array $data): Order
    {
        return Order::create($data);
    }

    /**
     * Create an order item.
     */
    public function createItem(array $data): OrderItem
    {
        return OrderItem::create($data);
    }

    /**
     * Create an order item allocation.
     */
    public function createAllocation(array $data): OrderItemAllocation
    {
        return OrderItemAllocation::create($data);
    }

    /**
     * Find an order or fail.
     */
    public function findOrFail(int $id): Order
    {
        return Order::findOrFail($id);
    }

    /**
     * Find an order item by order ID and product ID.
     */
    public function findOrderItem(int $orderId, int $productId): ?OrderItem
    {
        return OrderItem::where('order_id', $orderId)
            ->where('product_id', $productId)
            ->first();
    }

    /**
     * Get allocations for a specific order item.
     */
    public function getOrderItemAllocations(int $orderItemId): Collection
    {
        return OrderItemAllocation::where('order_item_id', $orderItemId)->get();
    }
}
