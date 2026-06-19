<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = ['name', 'category_id', 'price'];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<BatchProduct, $this>
     */
    public function batchProducts(): HasMany
    {
        return $this->hasMany(BatchProduct::class);
    }
}
