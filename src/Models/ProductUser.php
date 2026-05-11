<?php

namespace Lalalili\CommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUser extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('commerce.tables.product_user', 'product_user');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        $relation = $this->belongsTo($this->productModel());

        return $relation;
    }

    /**
     * @return class-string<Product>
     */
    private function productModel(): string
    {
        $model = config('commerce.models.product', Product::class);

        return is_string($model) && is_a($model, Product::class, true) ? $model : Product::class;
    }
}
