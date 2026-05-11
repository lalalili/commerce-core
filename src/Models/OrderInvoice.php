<?php

namespace Lalalili\CommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lalalili\CommerceCore\Enums\InvoiceStatus;
use Lalalili\CommerceCore\Enums\InvoiceType;

class OrderInvoice extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('commerce.tables.order_invoices', 'order_invoices');
    }

    public function casts(): array
    {
        return [
            'type'      => InvoiceType::class,
            'status'    => InvoiceStatus::class,
            'issued_at' => 'datetime',
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
     * @return class-string<Order>
     */
    private function orderModel(): string
    {
        $model = config('commerce.models.order', Order::class);

        return is_string($model) && is_a($model, Order::class, true) ? $model : Order::class;
    }
}
