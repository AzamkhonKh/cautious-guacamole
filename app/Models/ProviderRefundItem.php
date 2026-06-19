<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderRefundItem extends Model
{
    protected $table = 'provider_refund_items';

    protected $fillable = ['provider_refund_id', 'product_id', 'quantity'];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * @return BelongsTo<ProviderRefund, $this>
     */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(ProviderRefund::class, 'provider_refund_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
