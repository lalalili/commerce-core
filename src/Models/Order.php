<?php

namespace Lalalili\CommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lalalili\CommerceCore\Enums\InvoiceType;
use Lalalili\CommerceCore\Enums\OrderStatus;
use Lalalili\CommerceCore\Enums\PaymentStatus;

class Order extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('commerce.tables.orders', 'orders');
    }

    public function casts(): array
    {
        return [
            'payment_status' => PaymentStatus::class,
            'invoice_type' => InvoiceType::class,
            'invoice_code' => 'array',
            'status' => OrderStatus::class,
            'payment_time' => 'datetime',
            'cancel_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<OrderDetail, $this>
     */
    public function details(): HasMany
    {
        /** @var HasMany<OrderDetail, $this> $relation */
        $relation = $this->hasMany(config('commerce.models.order_detail', OrderDetail::class));

        return $relation;
    }

    /**
     * @return HasMany<OrderInvoice, $this>
     */
    public function invoices(): HasMany
    {
        /** @var HasMany<OrderInvoice, $this> $relation */
        $relation = $this->hasMany(config('commerce.models.order_invoice', OrderInvoice::class));

        return $relation;
    }

    /**
     * @return HasMany<PaymentLog, $this>
     */
    public function paymentLogs(): HasMany
    {
        /** @var HasMany<PaymentLog, $this> $relation */
        $relation = $this->hasMany(config('commerce.models.payment_log', PaymentLog::class), 'order_number', 'number');

        return $relation;
    }
}
