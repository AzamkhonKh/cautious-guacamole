<?php

namespace App\Repositories\Contracts;

use App\Models\ClientRefund;
use App\Models\ClientRefundAllocation;
use App\Models\ClientRefundItem;
use App\Models\ProviderRefund;
use App\Models\ProviderRefundItem;
use Illuminate\Support\Collection;

interface RefundRepositoryInterface
{
    /**
     * Create a provider refund.
     */
    public function createProviderRefund(array $data): ProviderRefund;

    /**
     * Create a provider refund item.
     */
    public function createProviderRefundItem(array $data): ProviderRefundItem;

    /**
     * Create a client refund.
     */
    public function createClientRefund(array $data): ClientRefund;

    /**
     * Create a client refund item.
     */
    public function createClientRefundItem(array $data): ClientRefundItem;

    /**
     * Create a client refund allocation.
     */
    public function createClientRefundAllocation(array $data): ClientRefundAllocation;

    /**
     * Get refunded quantities grouped by order item allocation ID.
     */
    public function getClientRefundedQuantities(Collection $allocationIds): Collection;
}
