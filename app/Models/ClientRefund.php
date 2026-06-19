<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientRefund extends Model
{
    protected $table = 'client_refunds';

    protected $fillable = ['order_id', 'refund_date'];

    protected $casts = [
        'refund_date' => 'datetime',
    ];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasMany<ClientRefundItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ClientRefundItem::class);
    }
}
