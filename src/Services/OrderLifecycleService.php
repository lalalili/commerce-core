<?php

namespace Lalalili\CommerceCore\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lalalili\CommerceCore\Models\Order;
use Lalalili\CommerceCore\Models\OrderDetail;
use Lalalili\CommerceCore\Models\Product;
use Lalalili\CommerceCore\Support\ModelAttributeMapper;
use Lalalili\CommerceCore\Support\OrderNumberGenerator;

class OrderLifecycleService
{
    public function __construct(
        private readonly OrderNumberGenerator $numberGenerator,
        private readonly EntitlementService $entitlements,
        private readonly ModelAttributeMapper $attributes,
    ) {
    }

    /**
     * @param  list<array{product_id:string, qty?:int, title?:string, list_price?:int, sales_price?:int, product_type?:int|null, company_id?:int|null}>  $items
     * @param  array<string, mixed>  $attributes
     */
    public function create(int $userId, array $items, array $attributes = []): Model
    {
        /** @var class-string<Order> $orderModel */
        $orderModel = config('commerce.models.order', Order::class);
        /** @var class-string<OrderDetail> $detailModel */
        $detailModel = config('commerce.models.order_detail', OrderDetail::class);
        /** @var class-string<Product> $productModel */
        $productModel = config('commerce.models.product', Product::class);

        return DB::transaction(function () use ($userId, $items, $attributes, $orderModel, $detailModel, $productModel): Model {
            $number = $attributes['number'] ?? $this->numberGenerator->generate();
            $normalizedItems = [];
            $totalListPrice = 0;
            $totalSalesPrice = 0;

            foreach ($items as $item) {
                /** @var Model|null $product */
                $product = $productModel::query()->find($item['product_id']);

                if (! $product instanceof Model) {
                    throw new \InvalidArgumentException("Product [{$item['product_id']}] does not exist.");
                }

                $quantity = max(1, (int) ($item['qty'] ?? 1));
                $listPrice = (int) ($item['list_price'] ?? $this->attributes->value($product, 'products', 'list_price', 0));
                $salesPrice = (int) ($item['sales_price'] ?? $this->attributes->value($product, 'products', 'sales_price', $listPrice));
                $totalListPrice += $listPrice * $quantity;
                $totalSalesPrice += $salesPrice * $quantity;

                $normalizedItems[] = [
                    'product'      => $product,
                    'qty'          => $quantity,
                    'title'        => (string) ($item['title'] ?? $this->attributes->value($product, 'products', 'title')),
                    'list_price'   => $listPrice,
                    'sales_price'  => $salesPrice,
                    'product_type' => $item['product_type'] ?? $this->attributes->value($product, 'products', 'type'),
                    'company_id'   => $item['company_id'] ?? $this->attributes->value($product, 'products', 'company_id'),
                ];
            }

            $orderAttributes = $this->attributes->filterForModel($orderModel, $this->attributes->map('orders', array_merge([
                'number'                 => $number,
                'user_id'                => $userId,
                'total_discount_amt'     => max(0, $totalListPrice - $totalSalesPrice),
                'total_sales_price'      => $totalSalesPrice,
                'payment_type'           => 1,
                'payment_status'         => $this->status('payment.pending'),
                'payment_status_message' => null,
                'invoice_type'           => $attributes['invoice_type'] ?? 1,
                'invoice_code'           => $attributes['invoice_code'] ?? null,
                'notes'                  => $attributes['notes'] ?? null,
                'status'                 => $this->status('order.pending'),
                'created_by'             => $attributes['created_by'] ?? $userId,
            ], $attributes)));

            /** @var Model $order */
            $order = $orderModel::query()->create($orderAttributes);

            foreach ($normalizedItems as $item) {
                /** @var Model $product */
                $product = $item['product'];

                $detailAttributes = $this->attributes->filterForModel($detailModel, $this->attributes->map('order_details', [
                    'order_id'       => $order->getKey(),
                    'order_number'   => data_get($order, 'number'),
                    'product_id'     => $product->getKey(),
                    'product_number' => $this->attributes->value($product, 'products', 'number', $product->getKey()),
                    'product_type'   => $item['product_type'],
                    'company_id'     => $item['company_id'],
                    'title'          => $item['title'],
                    'qty'            => $item['qty'],
                    'list_price'     => $item['list_price'],
                    'sales_price'    => $item['sales_price'],
                    'status'         => $this->status('order.pending'),
                    'created_by'     => $attributes['created_by'] ?? $userId,
                ]));

                $detailModel::query()->create($detailAttributes);
            }

            return $order->refresh();
        });
    }

    public function markPaid(
        string $orderNumber,
        string $paymentStatusMessage,
        CarbonInterface $paymentTime,
        ?int $updatedBy = null,
    ): ?Model {
        /** @var class-string<Order> $orderModel */
        $orderModel = config('commerce.models.order', Order::class);

        return DB::transaction(function () use ($orderNumber, $paymentStatusMessage, $paymentTime, $updatedBy, $orderModel): ?Model {
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
                'payment_status'         => $this->status('payment.complete'),
                'payment_status_message' => $paymentStatusMessage,
                'payment_time'           => $paymentTime,
                'payment_reconciled_at'  => now(),
                'status'                 => $paidStatus,
                'updated_by'             => $updatedBy ?? data_get($order, 'user_id'),
            ])));

            $order->{$this->detailsRelation()}()->update($this->attributes->filterForModel($this->detailModel(), $this->attributes->map('order_details', [
                'status'     => $paidStatus,
                'updated_by' => $updatedBy ?? data_get($order, 'user_id'),
            ])));

            $this->entitlements->grantOrder($order->refresh(), $updatedBy);

            return $order->refresh();
        });
    }

    public function cancel(string $orderNumber, ?int $updatedBy = null): ?Model
    {
        /** @var class-string<Order> $orderModel */
        $orderModel = config('commerce.models.order', Order::class);

        return DB::transaction(function () use ($orderNumber, $updatedBy, $orderModel): ?Model {
            /** @var Model|null $order */
            $order = $orderModel::query()
                ->with([$this->detailsRelation().'.product', $this->invoicesRelation()])
                ->where('number', $orderNumber)
                ->lockForUpdate()
                ->first();

            if (! $order instanceof Model) {
                return null;
            }

            if (! $this->statusEquals(data_get($order, 'status'), 'order.cancelled')) {
                $paymentStatus = $this->statusEquals(data_get($order, 'payment_status'), 'payment.complete')
                    ? $this->status('payment.refunded')
                    : $this->status('payment.cancelled');

                $order->update($this->attributes->filterForModel($orderModel, $this->attributes->map('orders', [
                    'status'         => $this->status('order.cancelled'),
                    'payment_status' => $paymentStatus,
                    'updated_by'     => $updatedBy ?? data_get($order, 'user_id'),
                    'cancel_at'      => now(),
                ])));

                $order->{$this->detailsRelation()}()->update($this->attributes->filterForModel($this->detailModel(), $this->attributes->map('order_details', [
                    'status'     => $this->status('order.cancelled'),
                    'updated_by' => $updatedBy ?? data_get($order, 'user_id'),
                ])));

                $order->{$this->invoicesRelation()}()
                    ->where('status', $this->status('invoice.complete'))
                    ->update($this->attributes->filterForModel($this->invoiceModel(), $this->attributes->map('order_invoices', [
                        'status'     => $this->status('invoice.cancelled'),
                        'updated_by' => $updatedBy ?? data_get($order, 'user_id'),
                    ])));
            }

            $this->entitlements->revokeOrder($order);

            return $order->refresh();
        });
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
        $model = config('commerce.models.order_invoice', \Lalalili\CommerceCore\Models\OrderInvoice::class);

        return is_string($model) && is_a($model, Model::class, true) ? $model : \Lalalili\CommerceCore\Models\OrderInvoice::class;
    }

    private function status(string $key, mixed $default = null): mixed
    {
        return config("commerce.statuses.{$key}", $default);
    }

    private function statusEquals(mixed $actual, string $expectedKey): bool
    {
        $expected = $this->status($expectedKey);

        if ($actual instanceof \BackedEnum) {
            $actual = $actual->value;
        }

        if ($expected instanceof \BackedEnum) {
            $expected = $expected->value;
        }

        return (string) $actual === (string) $expected;
    }
}
