<?php

namespace Lalalili\CommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lalalili\CommerceCore\Enums\OrderStatus;

class OrderDetail extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('commerce.tables.order_details', 'order_details');
    }

    public function casts(): array
    {
        return [
            'status' => OrderStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        $relation = $this->belongsTo($this->orderModel());

        return $relation;
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
     * @return class-string<Order>
     */
    private function orderModel(): string
    {
        $model = config('commerce.models.order', Order::class);

        return is_string($model) && is_a($model, Order::class, true) ? $model : Order::class;
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
