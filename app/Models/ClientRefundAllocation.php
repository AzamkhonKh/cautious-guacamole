<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientRefundAllocation extends Model
{
    protected $table = 'client_refund_allocations';

    protected $fillable = ['client_refund_item_id', 'order_item_allocation_id', 'quantity'];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * @return BelongsTo<ClientRefundItem, $this>
     */
    public function refundItem(): BelongsTo
    {
        return $this->belongsTo(ClientRefundItem::class);
    }

    /**
     * @return BelongsTo<OrderItemAllocation, $this>
     */
    public function orderItemAllocation(): BelongsTo
    {
        return $this->belongsTo(OrderItemAllocation::class);
    }
}
