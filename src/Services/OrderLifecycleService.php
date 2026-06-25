<?php

namespace Lalalili\CommerceCore\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lalalili\CommerceCore\DTOs\OrderItemData;
use Lalalili\CommerceCore\Events\OrderCancelled;
use Lalalili\CommerceCore\Events\OrderCreated;
use Lalalili\CommerceCore\Events\OrderFinished;
use Lalalili\CommerceCore\Events\OrderPaid;
use Lalalili\CommerceCore\Events\OrderShipped;
use Lalalili\CommerceCore\Models\Order;
use Lalalili\CommerceCore\Models\OrderDetail;
use Lalalili\CommerceCore\Models\OrderInvoice;
use Lalalili\CommerceCore\Models\Product;
use Lalalili\CommerceCore\Support\ModelAttributeMapper;
use Lalalili\CommerceCore\Support\OrderItemNormalizer;
use Lalalili\CommerceCore\Support\OrderNumberGenerator;

class OrderLifecycleService
{
    public function __construct(
        private readonly OrderNumberGenerator $numberGenerator,
        private readonly EntitlementService $entitlements,
        private readonly OrderLifecycleHookDispatcher $hooks,
        private readonly ModelAttributeMapper $attributes,
        private ?OrderItemNormalizer $itemNormalizer = null,
    ) {}

    /**
     * @param  list<array{product_id:int|string, qty?:int, title?:string, list_price?:int, sales_price?:int, product_type?:int|null, company_id?:int|null}>  $items
     * @param  array<string, mixed>  $attributes
     */
    public function create(int $userId, array $items, array $attributes = []): Model
    {
        if ($items === []) {
            throw new \InvalidArgumentException('Order items must not be empty.');
        }

        /** @var class-string<Order> $orderModel */
        $orderModel = config('commerce.models.order', Order::class);
        /** @var class-string<OrderDetail> $detailModel */
        $detailModel = config('commerce.models.order_detail', OrderDetail::class);
        /** @var class-string<Product> $productModel */
        $productModel = config('commerce.models.product', Product::class);

        $order = DB::transaction(function () use ($userId, $items, $attributes, $orderModel, $detailModel, $productModel): Model {
            $number = $attributes['number'] ?? $this->numberGenerator->generate();
            $normalizedItems = $this->resolveItemNormalizer()->normalize($items, $productModel);
            $totalListPrice = array_sum(array_map(
                static fn (OrderItemData $item): int => $item->totalListPrice(),
                $normalizedItems,
            ));
            $totalSalesPrice = array_sum(array_map(
                static fn (OrderItemData $item): int => $item->totalSalesPrice(),
                $normalizedItems,
            ));

            $orderAttributes = $this->attributes->filterForModel($orderModel, $this->attributes->map('orders', array_merge([
                'number' => $number,
                'user_id' => $userId,
                'total_discount_amt' => max(0, $totalListPrice - $totalSalesPrice),
                'total_sales_price' => $totalSalesPrice,
                'payment_type' => 1,
                'payment_status' => $this->status('payment.pending'),
                'payment_status_message' => null,
                'invoice_type' => $attributes['invoice_type'] ?? 1,
                'invoice_code' => $attributes['invoice_code'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'status' => $this->status('order.pending'),
                'created_by' => $attributes['created_by'] ?? $userId,
            ], $attributes)));

            /** @var Model $order */
            $order = $orderModel::query()->create($orderAttributes);

            foreach ($normalizedItems as $item) {
                $detailAttributes = $this->attributes->filterForModel($detailModel, $this->attributes->map('order_details', [
                    'order_id' => $order->getKey(),
                    'order_number' => data_get($order, 'number'),
                    'product_id' => $item->product->getKey(),
                    'product_number' => $this->attributes->value($item->product, 'products', 'number', $item->product->getKey()),
                    'product_type' => $item->productType,
                    'company_id' => $item->companyId,
                    'title' => $item->title,
                    'qty' => $item->quantity,
                    'list_price' => $item->listPrice,
                    'sales_price' => $item->salesPrice,
                    'status' => $this->status('order.pending'),
                    'created_by' => $attributes['created_by'] ?? $userId,
                ]));

                $detailModel::query()->create($detailAttributes);
            }

            if (count($normalizedItems) !== $order->{$this->detailsRelation()}()->count()) {
                throw new \RuntimeException('Order detail count does not match normalized order items.');
            }

            return $order->refresh();
        });

        Event::dispatch(new OrderCreated($order));

        return $order;
    }

    public function markPaid(
        string $orderNumber,
        string $paymentStatusMessage,
        CarbonInterface $paymentTime,
        int|string|null $updatedBy = null,
    ): ?Model {
        /** @var class-string<Order> $orderModel */
        $orderModel = config('commerce.models.order', Order::class);

        $transitioned = false;

        $order = DB::transaction(function () use ($orderNumber, $paymentStatusMessage, $paymentTime, $updatedBy, $orderModel, &$transitioned): ?Model {
            /** @var Model|null $order */
            $order = $orderModel::query()
                ->with([$this->detailsRelation().'.product'])
                ->where('number', $orderNumber)
                ->lockForUpdate()
                ->first();

            if (! $order instanceof Model) {
                return null;
            }

            if ($this->statusEquals(data_get($order, 'payment_status'), 'payment.complete')) {
                return $order;
            }

            $paidStatus = $this->status('order.paid', $this->status('order.complete'));

            $order->update($this->attributes->filterForModel($orderModel, $this->attributes->map('orders', [
                'payment_status' => $this->status('payment.complete'),
                'payment_status_message' => $paymentStatusMessage,
                'payment_time' => $paymentTime,
                'payment_reconciled_at' => now(),
                'status' => $paidStatus,
                'updated_by' => $updatedBy ?? data_get($order, 'user_id'),
            ])));

            $order->{$this->detailsRelation()}()->update($this->attributes->filterForModel($this->detailModel(), $this->attributes->map('order_details', [
                'status' => $paidStatus,
                'updated_by' => $updatedBy ?? data_get($order, 'user_id'),
            ])));

            $this->entitlements->grantOrder($order->refresh(), $updatedBy);

            $transitioned = true;

            return $order->refresh();
        });

        if ($order instanceof Model && $transitioned) {
            Event::dispatch(new OrderPaid($order, $paymentStatusMessage, $paymentTime, $updatedBy));
            $this->hooks->afterPaid($order);
        }

        return $order;
    }

    /**
     * @param  list<mixed>  $refundWhenOrderStatuses
     * @param  list<mixed>|null  $cancelInvoiceStatuses
     */
    public function cancel(
        string $orderNumber,
        int|string|null $updatedBy = null,
        array $refundWhenOrderStatuses = [],
        ?array $cancelInvoiceStatuses = null,
    ): ?Model {
        /** @var class-string<Order> $orderModel */
        $orderModel = config('commerce.models.order', Order::class);

        $transitioned = false;
        $refunded = false;
        $cancelInvoiceStatuses ??= [$this->status('invoice.complete')];

        $order = DB::transaction(function () use ($orderNumber, $updatedBy, $refundWhenOrderStatuses, $cancelInvoiceStatuses, $orderModel, &$transitioned, &$refunded): ?Model {
            /** @var Model|null $order */
            $order = $orderModel::query()
                ->with([$this->detailsRelation().'.product', $this->invoicesRelation()])
                ->where('number', $orderNumber)
                ->lockForUpdate()
                ->first();

            if (! $order instanceof Model) {
                return null;
            }

            $wasCancelled = $this->statusEquals(data_get($order, 'status'), 'order.cancelled');
            $wasPaid = $this->statusEquals(data_get($order, 'payment_status'), 'payment.complete')
                || ($refundWhenOrderStatuses !== [] && $this->statusIn(data_get($order, 'status'), $refundWhenOrderStatuses));

            if (! $wasCancelled) {
                $this->hooks->beforeCancelled($order);

                $paymentStatus = $wasPaid
                    ? $this->status('payment.refunded')
                    : $this->status('payment.cancelled');

                $order->update($this->attributes->filterForModel($orderModel, $this->attributes->map('orders', [
                    'status' => $this->status('order.cancelled'),
                    'payment_status' => $paymentStatus,
                    'updated_by' => $updatedBy ?? data_get($order, 'user_id'),
                    'cancel_at' => now(),
                ])));

                $order->{$this->detailsRelation()}()->update($this->attributes->filterForModel($this->detailModel(), $this->attributes->map('order_details', [
                    'status' => $this->status('order.cancelled'),
                    'updated_by' => $updatedBy ?? data_get($order, 'user_id'),
                ])));

                if ($cancelInvoiceStatuses !== []) {
                    $order->{$this->invoicesRelation()}()
                        ->whereIn('status', $cancelInvoiceStatuses)
                        ->update($this->attributes->filterForModel($this->invoiceModel(), $this->attributes->map('order_invoices', [
                            'status' => $this->status('invoice.cancelled'),
                            'updated_by' => $updatedBy ?? data_get($order, 'user_id'),
                        ])));
                }
                $transitioned = true;
                $refunded = $wasPaid;
            }

            $this->entitlements->revokeOrder($order);

            return $order->refresh();
        });

        if ($order instanceof Model && $transitioned) {
            Event::dispatch(new OrderCancelled($order, $updatedBy));
            $this->hooks->afterCancelled($order);

            if ($refunded) {
                $this->hooks->afterRefunded($order);
            }
        }

        return $order;
    }

    public function markRefunded(
        string $orderNumber,
        string $paymentStatusMessage,
        int|string|null $updatedBy = null,
    ): ?Model {
        /** @var class-string<Order> $orderModel */
        $orderModel = config('commerce.models.order', Order::class);

        $transitioned = false;

        $order = DB::transaction(function () use ($orderNumber, $paymentStatusMessage, $updatedBy, $orderModel, &$transitioned): ?Model {
            /** @var Model|null $order */
            $order = $orderModel::query()
                ->where('number', $orderNumber)
                ->lockForUpdate()
                ->first();

            if (! $order instanceof Model) {
                return null;
            }

            if ($this->statusEquals(data_get($order, 'payment_status'), 'payment.refunded')) {
                return $order;
            }

            $order->update($this->attributes->filterForModel($orderModel, $this->attributes->map('orders', [
                'payment_status' => $this->status('payment.refunded'),
                'payment_status_message' => $paymentStatusMessage,
                'payment_reconciled_at' => now(),
                'updated_by' => $updatedBy ?? data_get($order, 'user_id'),
            ])));

            $transitioned = true;

            return $order->refresh();
        });

        if ($order instanceof Model && $transitioned) {
            $this->hooks->afterRefunded($order);
        }

        return $order;
    }

    /**
     * @param  list<mixed>  $allowedFromStatuses
     */
    public function markShipped(string $orderNumber, int|string|null $updatedBy = null, array $allowedFromStatuses = []): ?Model
    {
        /** @var class-string<Order> $orderModel */
        $orderModel = config('commerce.models.order', Order::class);

        $transitioned = false;

        $order = DB::transaction(function () use ($orderNumber, $updatedBy, $allowedFromStatuses, $orderModel, &$transitioned): ?Model {
            /** @var Model|null $order */
            $order = $orderModel::query()
                ->where('number', $orderNumber)
                ->lockForUpdate()
                ->first();

            if (! $order instanceof Model) {
                return null;
            }

            if (
                $this->statusEquals(data_get($order, 'status'), 'order.shipping')
                && data_get($order, 'shipping_at') !== null
            ) {
                return $order;
            }

            if ($allowedFromStatuses !== [] && ! $this->statusIn(data_get($order, 'status'), $allowedFromStatuses)) {
                return $order;
            }

            $shippingStatus = $this->status('order.shipping');

            $order->update($this->attributes->filterForModel($orderModel, $this->attributes->map('orders', [
                'status' => $shippingStatus,
                'updated_by' => $updatedBy ?? data_get($order, 'user_id'),
                'shipping_at' => now(),
            ])));

            $order->{$this->detailsRelation()}()->update($this->attributes->filterForModel($this->detailModel(), $this->attributes->map('order_details', [
                'status' => $shippingStatus,
                'updated_by' => $updatedBy ?? data_get($order, 'user_id'),
            ])));

            $transitioned = true;

            return $order->refresh();
        });

        if ($order instanceof Model && $transitioned) {
            Event::dispatch(new OrderShipped($order, updatedBy: $updatedBy));
            $this->hooks->afterShipped($order);
        }

        return $order;
    }

    public function markFinished(string $orderNumber, int|string|null $updatedBy = null): ?Model
    {
        /** @var class-string<Order> $orderModel */
        $orderModel = config('commerce.models.order', Order::class);

        $transitioned = false;

        $order = DB::transaction(function () use ($orderNumber, $updatedBy, $orderModel, &$transitioned): ?Model {
            /** @var Model|null $order */
            $order = $orderModel::query()
                ->where('number', $orderNumber)
                ->lockForUpdate()
                ->first();

            if (! $order instanceof Model) {
                return null;
            }

            $isFinished = $this->statusEquals(data_get($order, 'status'), 'order.finished');
            $isPaid = $this->statusEquals(data_get($order, 'payment_status'), 'payment.complete');
            if ($isFinished && $isPaid) {
                return $order;
            }

            $finishedStatus = $this->status('order.finished');

            $order->update($this->attributes->filterForModel($orderModel, $this->attributes->map('orders', [
                'status' => $finishedStatus,
                'payment_status' => $this->status('payment.complete'),
                'updated_by' => $updatedBy ?? data_get($order, 'user_id'),
            ])));

            $order->{$this->detailsRelation()}()->update($this->attributes->filterForModel($this->detailModel(), $this->attributes->map('order_details', [
                'status' => $finishedStatus,
                'updated_by' => $updatedBy ?? data_get($order, 'user_id'),
            ])));

            $transitioned = true;

            return $order->refresh();
        });

        if ($order instanceof Model && $transitioned) {
            Event::dispatch(new OrderFinished($order, $updatedBy));
            $this->hooks->afterFinished($order);
        }

        return $order;
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    public function detailsGroupedByTax(Model $order): array
    {
        $groups = [];
        $detailsRelation = $this->detailsRelation();
        $order->loadMissing([$detailsRelation.'.product']);

        foreach ($order->getRelationValue($detailsRelation) ?? [] as $detail) {
            if (! $detail instanceof Model || ! $detail->getRelationValue('product') instanceof Model) {
                continue;
            }

            /** @var Model $product */
            $product = $detail->getRelationValue('product');
            $tax = (int) $this->attributes->value($product, 'products', 'tax', 1);
            $groups[$tax][] = $detail->toArray();
        }

        return $groups;
    }

    private function detailsRelation(): string
    {
        $relation = config('commerce.relationships.order_details', 'details');

        return is_string($relation) && $relation !== '' ? $relation : 'details';
    }

    private function invoicesRelation(): string
    {
        $relation = config('commerce.relationships.order_invoices', 'invoices');

        return is_string($relation) && $relation !== '' ? $relation : 'invoices';
    }

    /**
     * @return class-string<Model>
     */
    private function detailModel(): string
    {
        $model = config('commerce.models.order_detail', OrderDetail::class);

        return is_string($model) && is_a($model, Model::class, true) ? $model : OrderDetail::class;
    }

    /**
     * @return class-string<Model>
     */
    private function invoiceModel(): string
    {
        $model = config('commerce.models.order_invoice', OrderInvoice::class);

        return is_string($model) && is_a($model, Model::class, true) ? $model : OrderInvoice::class;
    }

    private function status(string $key, mixed $default = null): mixed
    {
        return config("commerce.statuses.{$key}", $default);
    }

    private function statusEquals(mixed $actual, string $expectedKey): bool
    {
        $expected = $this->status($expectedKey);

        return $this->normalizeStatusValue($actual) === $this->normalizeStatusValue($expected);
    }

    /**
     * @param  list<mixed>  $expectedValues
     */
    private function statusIn(mixed $actual, array $expectedValues): bool
    {
        $actualValue = $this->normalizeStatusValue($actual);

        foreach ($expectedValues as $expectedValue) {
            if ($actualValue === $this->normalizeStatusValue($expectedValue)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeStatusValue(mixed $status): string
    {
        if ($status instanceof \BackedEnum) {
            $status = $status->value;
        }

        return (string) $status;
    }

    private function resolveItemNormalizer(): OrderItemNormalizer
    {
        if ($this->itemNormalizer instanceof OrderItemNormalizer) {
            return $this->itemNormalizer;
        }

        return $this->itemNormalizer = new OrderItemNormalizer($this->attributes);
    }
}
