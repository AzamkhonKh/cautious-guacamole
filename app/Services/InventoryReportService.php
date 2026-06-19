<?php

namespace App\Services;

use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ReportRepositoryInterface;
use Illuminate\Support\Collection;

class InventoryReportService
{
    protected ReportRepositoryInterface $reportRepository;
    protected ProductRepositoryInterface $productRepository;

    public function __construct(
        ReportRepositoryInterface $reportRepository,
        ProductRepositoryInterface $productRepository
    ) {
        $this->reportRepository = $reportRepository;
        $this->productRepository = $productRepository;
    }

    /**
     * Fetch available products for ordering.
     */
    public function getAvailableProducts(): Collection
    {
        return $this->productRepository->getAvailableProducts();
    }

    /**
     * Calculate Remaining Quantities in Storage by Date.
     */
    public function getStorageQuantities(string $date): Collection
    {
        $purchased = $this->reportRepository->getPurchasedQuantities($date);
        $providerRefunded = $this->reportRepository->getProviderRefundedQuantities($date);
        $sold = $this->reportRepository->getSoldQuantities($date);
        $clientRefunded = $this->reportRepository->getClientRefundedQuantitiesForReport($date);

        $map = [];

        foreach ($purchased as $p) {
            $key = "{$p->product_id}-{$p->storage_id}";
            $map[$key] = [
                'product_id' => (int) $p->product_id,
                'storage_id' => (int) $p->storage_id,
                'qty' => (int) $p->total_qty,
            ];
        }

        foreach ($providerRefunded as $pr) {
            $key = "{$pr->product_id}-{$pr->storage_id}";
            if (isset($map[$key])) {
                $map[$key]['qty'] -= (int) $pr->total_qty;
            }
        }

        foreach ($sold as $s) {
            $key = "{$s->product_id}-{$s->storage_id}";
            if (isset($map[$key])) {
                $map[$key]['qty'] -= (int) $s->total_qty;
            }
        }

        foreach ($clientRefunded as $cr) {
            $key = "{$cr->product_id}-{$cr->storage_id}";
            if (isset($map[$key])) {
                $map[$key]['qty'] += (int) $cr->total_qty;
            }
        }

        $productIds = collect($map)->pluck('product_id')->unique()->all();
        $storageIds = collect($map)->pluck('storage_id')->unique()->all();

        $products = \App\Models\Product::whereIn('id', $productIds)->get()->keyBy('id');
        $storages = \App\Models\Storage::whereIn('id', $storageIds)->get()->keyBy('id');

        return collect($map)->map(function ($item) use ($products, $storages) {
            $product = $products->get($item['product_id']);
            $storage = $storages->get($item['storage_id']);

            return [
                'product_id' => $item['product_id'],
                'product_name' => $product ? $product->name : 'Unknown Product',
                'storage_id' => $item['storage_id'],
                'storage_name' => $storage ? $storage->name : 'Unknown Storage',
                'qty' => max(0, $item['qty']), // Prevent negative edge cases
            ];
        })->values();
    }

    /**
     * Calculate Profit per Batch, factoring in refunds for accurate results.
     */
    public function getBatchProfit(): Collection
    {
        $allocations = $this->reportRepository->getAllocationsWithPrices();
        $refunds = $this->reportRepository->getTotalClientRefundsPerAllocation();
        $batches = $this->reportRepository->getAllBatches();

        $batchProfits = [];

        foreach ($batches as $batch) {
            $batchProfits[$batch->id] = [
                'batch_id' => $batch->id,
                'provider_name' => $batch->provider->name,
                'storage_name' => $batch->storage->name,
                'profit' => 0.0,
            ];
        }

        foreach ($allocations as $alloc) {
            $refundedQty = $refunds->has($alloc->allocation_id)
                ? (int) $refunds->get($alloc->allocation_id)->total_refunded
                : 0;

            $netSoldQty = $alloc->allocated_quantity - $refundedQty;

            $purchasePrice = (float) $alloc->purchase_price;
            $sellingPrice = (float) $alloc->selling_price;

            $itemProfit = $netSoldQty * ($sellingPrice - $purchasePrice);

            if (isset($batchProfits[$alloc->batch_id])) {
                $batchProfits[$alloc->batch_id]['profit'] += $itemProfit;
            }
        }

        return collect($batchProfits)->map(function ($item) {
            $item['profit'] = round($item['profit'], 2);

            return $item;
        })->values();
    }
}
