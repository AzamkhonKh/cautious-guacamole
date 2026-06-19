<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Support\Collection;

interface ProductRepositoryInterface
{
    /**
     * Fetch available products with total remaining quantities and category names.
     */
    public function getAvailableProducts(): Collection;

    /**
     * Find a product by ID or fail.
     */
    public function findOrFail(int $id): Product;
}
