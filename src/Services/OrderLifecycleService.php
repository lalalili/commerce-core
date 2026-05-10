<?php

namespace Lalalili\CommerceCore\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lalalili\CommerceCore\Models\Order;
use Lalalili\CommerceCore\Models\OrderDetail;
use Lalalili\CommerceCore\Models\Product;
use Lalalili\CommerceCore\Support\OrderNumberGenerator;

class OrderLifecycleService
{
    public function __construct(
        private readonly OrderNumberGenerator $numberGenerator,
        private readonly EntitlementService $entitlements,
    ) {}

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
                $listPrice = (int) ($item['list_price'] ?? $product->list_price ?? 0);
                $salesPrice = (int) ($item['sales_price'] ?? $product->sales_price ?? $listPrice);
                $totalListPrice += $listPrice * $quantity;
                $totalSalesPrice += $salesPrice * $quantity;

                $normalizedItems[] = [
                    'product' => $product,
                    'qty' => $quantity,
                    'title' => (string) ($item['title'] ?? $product->title),
                    'list_price' => $listPrice,
                    'sales_price' => $salesPrice,
                    'product_type' => $item['product_type'] ?? $product->type ?? null,
                    'company_id' => $item['company_id'] ?? $product->company_id ?? null,
                ];
            }

            /** @var Model $order */
            $order = $orderModel::query()->create(array_merge([
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
            ], $attributes));

            foreach ($normalizedItems as $item) {
                /** @var Model $product */
                $product = $item['product'];

                $detailModel::query()->create([
                    'order_id' => $order->getKey(),
                    'order_number' => $order->number,
                    'product_id' => $product->getKey(),
                    'product_type' => $item['product_type'],
                    'company_id' => $item['company_id'],
                    'title' => $item['title'],
                    'qty' => $item['qty'],
                    'list_price' => $item['list_price'],
                    'sales_price' => $item['sales_price'],
                    'status' => $this->status('order.pending'),
                    'created_by' => $attributes['created_by'] ?? $userId,
                ]);
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

            if ($this->statusEquals($order->payment_status, 'payment.complete')) {
                return $order;
            }

            $order->update([
                'payment_status' => $this->status('payment.complete'),
                'payment_status_message' => $paymentStatusMessage,
                'payment_time' => $paymentTime,
                'status' => $this->status('order.complete'),
                'updated_by' => $updatedBy ?? $order->user_id,
            ]);

            $order->{$this->detailsRelation()}()->update([
                'status' => $this->status('order.complete'),
                'updated_by' => $updatedBy ?? $order->user_id,
            ]);

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

            if (! $this->statusEquals($order->status, 'order.cancelled')) {
                $paymentStatus = $this->statusEquals($order->payment_status, 'payment.complete')
                    ? $this->status('payment.refunded')
                    : $this->status('payment.cancelled');

                $order->update([
                    'status' => $this->status('order.cancelled'),
                    'payment_status' => $paymentStatus,
                    'updated_by' => $updatedBy ?? $order->user_id,
                    'cancel_at' => now(),
                ]);

                $order->{$this->detailsRelation()}()->update([
                    'status' => $this->status('order.cancelled'),
                    'updated_by' => $updatedBy ?? $order->user_id,
                ]);

                $order->{$this->invoicesRelation()}()
                    ->where('status', $this->status('invoice.complete'))
                    ->update([
                        'status' => $this->status('invoice.cancelled'),
                        'updated_by' => $updatedBy ?? $order->user_id,
                    ]);
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
            $tax = (int) ($product->tax ?? 1);
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

    private function status(string $key): mixed
    {
        return config("commerce.statuses.{$key}");
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
