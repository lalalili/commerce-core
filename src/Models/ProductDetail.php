<?php

namespace Lalalili\CommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDetail extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('commerce.tables.product_details', 'product_details');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        /** @var BelongsTo<Product, $this> $relation */
        $relation = $this->belongsTo(config('commerce.models.product', Product::class));

        return $relation;
    }
}
