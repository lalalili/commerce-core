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
        /** @var BelongsTo<Order, $this> $relation */
        $relation = $this->belongsTo(config('commerce.models.order', Order::class));

        return $relation;
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
