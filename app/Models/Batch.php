<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    protected $fillable = ['provider_id', 'storage_id', 'purchase_date'];

    protected $casts = [
        'purchase_date' => 'datetime',
    ];

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * @return BelongsTo<Storage, $this>
     */
    public function storage(): BelongsTo
    {
        return $this->belongsTo(Storage::class);
    }

    /**
     * @return HasMany<BatchProduct, $this>
     */
    public function batchProducts(): HasMany
    {
        return $this->hasMany(BatchProduct::class);
    }

    /**
     * @return HasMany<ProviderRefund, $this>
     */
    public function providerRefunds(): HasMany
    {
        return $this->hasMany(ProviderRefund::class);
    }
}
