<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BatchProduct extends Model
{
    protected $table = 'batch_products';

    protected $fillable = ['batch_id', 'product_id', 'quantity', 'purchase_price', 'remaining_quantity'];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'quantity' => 'integer',
        'remaining_quantity' => 'integer',
    ];

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<OrderItemAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(OrderItemAllocation::class);
    }
}
