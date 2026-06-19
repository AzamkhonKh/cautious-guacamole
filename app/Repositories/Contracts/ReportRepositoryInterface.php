<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface ReportRepositoryInterface
{
    /**
     * Get purchased product quantities by product and storage on/before date.
     */
    public function getPurchasedQuantities(string $date): Collection;

    /**
     * Get provider refunded product quantities by product and storage on/before date.
     */
    public function getProviderRefundedQuantities(string $date): Collection;

    /**
     * Get sold product quantities by product and storage on/before date.
     */
    public function getSoldQuantities(string $date): Collection;

    /**
     * Get client refunded product quantities by product and storage on/before date.
     */
    public function getClientRefundedQuantitiesForReport(string $date): Collection;

    /**
     * Get all allocations with their batch products and prices.
     */
    public function getAllocationsWithPrices(): Collection;

    /**
     * Get total client refunds grouped by allocation ID.
     */
    public function getTotalClientRefundsPerAllocation(): Collection;

    /**
     * Get all batches with provider and storage relations.
     */
    public function getAllBatches(): Collection;
}
