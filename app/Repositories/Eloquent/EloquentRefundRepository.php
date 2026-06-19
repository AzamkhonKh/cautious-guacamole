<?php

namespace App\Repositories\Eloquent;

use App\Models\ClientRefund;
use App\Models\ClientRefundAllocation;
use App\Models\ClientRefundItem;
use App\Models\ProviderRefund;
use App\Models\ProviderRefundItem;
use App\Repositories\Contracts\RefundRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentRefundRepository implements RefundRepositoryInterface
{
    /**
     * Create a provider refund.
     */
    public function createProviderRefund(array $data): ProviderRefund
    {
        return ProviderRefund::create($data);
    }

    /**
     * Create a provider refund item.
     */
    public function createProviderRefundItem(array $data): ProviderRefundItem
    {
        return ProviderRefundItem::create($data);
    }

    /**
     * Create a client refund.
     */
    public function createClientRefund(array $data): ClientRefund
    {
        return ClientRefund::create($data);
    }

    /**
     * Create a client refund item.
     */
    public function createClientRefundItem(array $data): ClientRefundItem
    {
        return ClientRefundItem::create($data);
    }

    /**
     * Create a client refund allocation.
     */
    public function createClientRefundAllocation(array $data): ClientRefundAllocation
    {
        return ClientRefundAllocation::create($data);
    }

    /**
     * Get refunded quantities grouped by order item allocation ID.
     */
    public function getClientRefundedQuantities(Collection $allocationIds): Collection
    {
        return DB::table('client_refund_allocations')
            ->whereIn('order_item_allocation_id', $allocationIds)
            ->groupBy('order_item_allocation_id')
            ->select('order_item_allocation_id', DB::raw('SUM(quantity) as total_refunded'))
            ->get()
            ->keyBy('order_item_allocation_id');
    }
}
