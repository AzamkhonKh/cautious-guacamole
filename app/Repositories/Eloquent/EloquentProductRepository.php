<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentProductRepository implements ProductRepositoryInterface
{
    /**
     * Fetch available products with total remaining quantities and category names.
     */
    public function getAvailableProducts(): Collection
    {
        return \App\Models\AvailableProductMaterialized::select('product_id as id', 'name', 'category_name', 'price', 'qty')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => (int) $item->id,
                    'name' => $item->name,
                    'category_name' => $item->category_name,
                    'price' => (float) $item->price,
                    'qty' => (int) $item->qty,
                ];
            });
    }

    /**
     * Find a product by ID or fail.
     */
    public function findOrFail(int $id): Product
    {
        return Product::findOrFail($id);
    }
}
