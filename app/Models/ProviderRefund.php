<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderRefund extends Model
{
    protected $table = 'provider_refunds';

    protected $fillable = ['batch_id', 'refund_date'];

    protected $casts = [
        'refund_date' => 'datetime',
    ];

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * @return HasMany<ProviderRefundItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProviderRefundItem::class);
    }
}
