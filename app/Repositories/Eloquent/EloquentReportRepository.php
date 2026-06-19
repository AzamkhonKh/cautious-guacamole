<?php

namespace App\Repositories\Eloquent;

use App\Models\Batch;
use App\Repositories\Contracts\ReportRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentReportRepository implements ReportRepositoryInterface
{
    /**
     * Get purchased product quantities by product and storage on/before date.
     */
    public function getPurchasedQuantities(string $date): Collection
    {
        return DB::table('batch_products')
            ->join('batches', 'batch_products.batch_id', '=', 'batches.id')
            ->where('batches.purchase_date', '<=', $date)
            ->groupBy('batch_products.product_id', 'batches.storage_id')
            ->select('batch_products.product_id', 'batches.storage_id', DB::raw('SUM(batch_products.quantity) as total_qty'))
            ->get();
    }

    /**
     * Get provider refunded product quantities by product and storage on/before date.
     */
    public function getProviderRefundedQuantities(string $date): Collection
    {
        return DB::table('provider_refund_items')
            ->join('provider_refunds', 'provider_refund_items.provider_refund_id', '=', 'provider_refunds.id')
            ->join('batches', 'provider_refunds.batch_id', '=', 'batches.id')
            ->where('provider_refunds.refund_date', '<=', $date)
            ->groupBy('provider_refund_items.product_id', 'batches.storage_id')
            ->select('provider_refund_items.product_id', 'batches.storage_id', DB::raw('SUM(provider_refund_items.quantity) as total_qty'))
            ->get();
    }

    /**
     * Get sold product quantities by product and storage on/before date.
     */
    public function getSoldQuantities(string $date): Collection
    {
        return DB::table('order_item_allocations')
            ->join('order_items', 'order_item_allocations.order_item_id', '=', 'order_items.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('batch_products', 'order_item_allocations.batch_product_id', '=', 'batch_products.id')
            ->join('batches', 'batch_products.batch_id', '=', 'batches.id')
            ->where('orders.order_date', '<=', $date)
            ->groupBy('order_items.product_id', 'batches.storage_id')
            ->select('order_items.product_id', 'batches.storage_id', DB::raw('SUM(order_item_allocations.quantity) as total_qty'))
            ->get();
    }

    /**
     * Get client refunded product quantities by product and storage on/before date.
     */
    public function getClientRefundedQuantitiesForReport(string $date): Collection
    {
        return DB::table('client_refund_allocations')
            ->join('client_refund_items', 'client_refund_allocations.client_refund_item_id', '=', 'client_refund_items.id')
            ->join('client_refunds', 'client_refund_items.client_refund_id', '=', 'client_refunds.id')
            ->join('order_item_allocations', 'client_refund_allocations.order_item_allocation_id', '=', 'order_item_allocations.id')
            ->join('batch_products', 'order_item_allocations.batch_product_id', '=', 'batch_products.id')
            ->join('batches', 'batch_products.batch_id', '=', 'batches.id')
            ->where('client_refunds.refund_date', '<=', $date)
            ->groupBy('client_refund_items.product_id', 'batches.storage_id')
            ->select('client_refund_items.product_id', 'batches.storage_id', DB::raw('SUM(client_refund_allocations.quantity) as total_qty'))
            ->get();
    }

    /**
     * Get all allocations with their batch products and prices.
     */
    public function getAllocationsWithPrices(): Collection
    {
        return DB::table('order_item_allocations')
            ->join('order_items', 'order_item_allocations.order_item_id', '=', 'order_items.id')
            ->join('batch_products', 'order_item_allocations.batch_product_id', '=', 'batch_products.id')
            ->select(
                'order_item_allocations.id as allocation_id',
                'batch_products.batch_id',
                'order_item_allocations.quantity as allocated_quantity',
                'order_items.price as selling_price',
                'batch_products.purchase_price'
            )
            ->get();
    }

    /**
     * Get total client refunds grouped by allocation ID.
     */
    public function getTotalClientRefundsPerAllocation(): Collection
    {
        return DB::table('client_refund_allocations')
            ->groupBy('order_item_allocation_id')
            ->select('order_item_allocation_id', DB::raw('SUM(quantity) as total_refunded'))
            ->get()
            ->keyBy('order_item_allocation_id');
    }

    /**
     * Get all batches with provider and storage relations.
     */
    public function getAllBatches(): Collection
    {
        return Batch::with(['provider', 'storage'])->get();
    }
}
