<?php

namespace Lalalili\CommerceCore\Services;

use Illuminate\Database\Eloquent\Model;
use Lalalili\CommerceCore\DTOs\PaymentApplicationData;
use Lalalili\CommerceCore\Enums\PaymentApplicationOutcome;
use Lalalili\CommerceCore\Models\Order;
use Lalalili\CommerceCore\Support\ModelAttributeMapper;

class PaymentApplicationService
{
    public function __construct(
        private readonly PaymentLogService $paymentLogs,
        private readonly OrderLifecycleService $orders,
        private readonly ModelAttributeMapper $attributes,
    ) {}

    public function apply(PaymentApplicationData $payment, ?int $updatedBy = null): ?Model
    {
        $this->paymentLogs->record(
            orderNumber: $payment->orderNumber,
            payload: $payment->payload,
            statusCode: $payment->statusCode,
            statusMessage: $payment->statusMessage,
        );

        $order = $this->findOrder($payment->orderNumber);

        if (! $order instanceof Model) {
            return null;
        }

        return match ($payment->outcome) {
            PaymentApplicationOutcome::Paid => $this->applyPaid($order, $payment, $updatedBy),
            PaymentApplicationOutcome::Refunded => $this->orders->markRefunded(
                $payment->orderNumber,
                $payment->orderMessage(),
                $updatedBy,
            ),
            default => $this->updatePaymentMessage($order, $payment->orderMessage()),
        };
    }

    private function applyPaid(Model $order, PaymentApplicationData $payment, ?int $updatedBy): ?Model
    {
        if ($payment->paidAt === null) {
            return $this->updatePaymentMessage($order, $payment->orderMessage());
        }

        if ($payment->amount !== null && $payment->amount !== $this->orderAmount($order)) {
            $this->paymentLogs->record(
                orderNumber: $payment->orderNumber,
                payload: $payment->payload,
                statusCode: $payment->statusCode,
                statusMessage: $payment->gatewayLabel.'付款金額'.$payment->amount.'與訂單金額不符',
            );

            return $order->refresh();
        }

        return $this->orders->markPaid(
            $payment->orderNumber,
            $payment->orderMessage(),
            $payment->paidAt,
            $updatedBy,
        );
    }

    private function updatePaymentMessage(Model $order, string $message): Model
    {
        $orderModel = $order::class;
        $order->update($this->attributes->filterForModel($orderModel, $this->attributes->map('orders', [
            'payment_status_message' => $message,
        ])));

        return $order->refresh();
    }

    private function orderAmount(Model $order): int
    {
        return (int) $this->attributes->value($order, 'orders', 'total_sales_price', 0);
    }

    private function findOrder(string $orderNumber): ?Model
    {
        /** @var class-string<Model> $orderModel */
        $orderModel = $this->orderModel();

        /** @var Model|null $order */
        $order = $orderModel::query()
            ->where($this->attributes->column('orders', 'number', 'number') ?? 'number', $orderNumber)
            ->first();

        return $order;
    }

    /**
     * @return class-string<Model>
     */
    private function orderModel(): string
    {
        $model = config('commerce.models.order', Order::class);

        return is_string($model) && is_a($model, Model::class, true) ? $model : Order::class;
    }
}
