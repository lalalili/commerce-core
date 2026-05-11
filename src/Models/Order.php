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
            'invoice_type'   => InvoiceType::class,
            'invoice_code'   => 'array',
            'status'         => OrderStatus::class,
            'payment_time'   => 'datetime',
            'cancel_at'      => 'datetime',
        ];
    }

    /**
     * @return HasMany<OrderDetail, $this>
     */
    public function details(): HasMany
    {
        $relation = $this->hasMany($this->orderDetailModel());

        return $relation;
    }

    /**
     * @return HasMany<OrderInvoice, $this>
     */
    public function invoices(): HasMany
    {
        $relation = $this->hasMany($this->orderInvoiceModel());

        return $relation;
    }

    /**
     * @return HasMany<PaymentLog, $this>
     */
    public function paymentLogs(): HasMany
    {
        $relation = $this->hasMany($this->paymentLogModel(), 'order_number', 'number');

        return $relation;
    }

    /**
     * @return class-string<OrderDetail>
     */
    private function orderDetailModel(): string
    {
        $model = config('commerce.models.order_detail', OrderDetail::class);

        return is_string($model) && is_a($model, OrderDetail::class, true) ? $model : OrderDetail::class;
    }

    /**
     * @return class-string<OrderInvoice>
     */
    private function orderInvoiceModel(): string
    {
        $model = config('commerce.models.order_invoice', OrderInvoice::class);

        return is_string($model) && is_a($model, OrderInvoice::class, true) ? $model : OrderInvoice::class;
    }

    /**
     * @return class-string<PaymentLog>
     */
    private function paymentLogModel(): string
    {
        $model = config('commerce.models.payment_log', PaymentLog::class);

        return is_string($model) && is_a($model, PaymentLog::class, true) ? $model : PaymentLog::class;
    }
}
