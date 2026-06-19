<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItemAllocation extends Model
{
    protected $table = 'order_item_allocations';

    protected $fillable = ['order_item_id', 'batch_product_id', 'quantity'];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return BelongsTo<BatchProduct, $this>
     */
    public function batchProduct(): BelongsTo
    {
        return $this->belongsTo(BatchProduct::class);
    }

    /**
     * @return HasMany<ClientRefundAllocation, $this>
     */
    public function refundAllocations(): HasMany
    {
        return $this->hasMany(ClientRefundAllocation::class);
    }
}
