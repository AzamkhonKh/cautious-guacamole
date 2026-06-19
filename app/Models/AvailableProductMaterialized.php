<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailableProductMaterialized extends Model
{
    protected $table = 'available_products_materialized';

    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $fillable = ['product_id', 'name', 'category_name', 'price', 'qty'];

    /**
     * Get the product associated with the materialized view.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
