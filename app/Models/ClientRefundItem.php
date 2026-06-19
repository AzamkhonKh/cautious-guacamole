<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientRefundItem extends Model
{
    protected $table = 'client_refund_items';

    protected $fillable = ['client_refund_id', 'product_id', 'quantity'];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * @return BelongsTo<ClientRefund, $this>
     */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(ClientRefund::class, 'client_refund_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<ClientRefundAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(ClientRefundAllocation::class);
    }
}
