<?php

namespace Lalalili\CommerceCore\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lalalili\CommerceCore\Enums\InvoiceStatus;
use Lalalili\CommerceCore\Enums\OrderStatus;
use Lalalili\CommerceCore\Enums\PaymentStatus;
use Lalalili\CommerceCore\Models\Order;
use Lalalili\CommerceCore\Models\OrderDetail;
use Lalalili\CommerceCore\Models\Product;
use Lalalili\CommerceCore\Support\OrderNumberGenerator;

class OrderLifecycleService
{
    public function __construct(
        private readonly OrderNumberGenerator $numberGenerator,
        private readonly EntitlementService $entitlements,
    ) {
    }

    /**
     * @param  list<array{product_id:string, qty?:int, title?:string, list_price?:int, sales_price?:int, product_type?:int|null, company_id?:int|null}>  $items
     * @param  array<string, mixed>  $attributes
     */
    public function create(int $userId, array $items, array $attributes = []): Order
    {
        /** @var class-string<Order> $orderModel */
        $orderModel = config('commerce.models.order', Order::class);
        /** @var class-string<OrderDetail> $detailModel */
        $detailModel = config('commerce.models.order_detail', OrderDetail::class);
        /** @var class-string<Product> $productModel */
        $productModel = config('commerce.models.product', Product::class);

        return DB::transaction(function () use ($userId, $items, $attributes, $orderModel, $detailModel, $productModel): Order {
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
                    'product'      => $product,
                    'qty'          => $quantity,
                    'title'        => (string) ($item['title'] ?? $product->title),
                    'list_price'   => $listPrice,
                    'sales_price'  => $salesPrice,
                    'product_type' => $item['product_type'] ?? $product->type ?? null,
                    'company_id'   => $item['company_id'] ?? $product->company_id ?? null,
                ];
            }

            /** @var Order $order */
            $order = $orderModel::query()->create(array_merge([
                'number'                 => $number,
                'user_id'                => $userId,
                'total_discount_amt'     => max(0, $totalListPrice - $totalSalesPrice),
                'total_sales_price'      => $totalSalesPrice,
                'payment_type'           => 1,
                'payment_status'         => PaymentStatus::Pending,
                'payment_status_message' => null,
                'invoice_type'           => $attributes['invoice_type'] ?? 1,
                'invoice_code'           => $attributes['invoice_code'] ?? null,
                'notes'                  => $attributes['notes'] ?? null,
                'status'                 => OrderStatus::Pending,
                'created_by'             => $attributes['created_by'] ?? $userId,
            ], $attributes));

            foreach ($normalizedItems as $item) {
                /** @var Model $product */
                $product = $item['product'];

                $detailModel::query()->create([
                    'order_id'     => $order->getKey(),
                    'order_number' => $order->number,
                    'product_id'   => $product->getKey(),
                    'product_type' => $item['product_type'],
                    'company_id'   => $item['company_id'],
                    'title'        => $item['title'],
                    'qty'          => $item['qty'],
                    'list_price'   => $item['list_price'],
                    'sales_price'  => $item['sales_price'],
                    'status'       => OrderStatus::Pending,
                    'created_by'   => $attributes['created_by'] ?? $userId,
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
    ): ?Order {
        /** @var class-string<Order> $orderModel */
        $orderModel = config('commerce.models.order', Order::class);

        return DB::transaction(function () use ($orderNumber, $paymentStatusMessage, $paymentTime, $updatedBy, $orderModel): ?Order {
            /** @var Order|null $order */
            $order = $orderModel::query()
                ->with(['details.product'])
                ->where('number', $orderNumber)
                ->lockForUpdate()
                ->first();

            if (! $order instanceof Order) {
                return null;
            }

            if ($order->payment_status === PaymentStatus::Complete) {
                return $order;
            }

            $order->update([
                'payment_status'         => PaymentStatus::Complete,
                'payment_status_message' => $paymentStatusMessage,
                'payment_time'           => $paymentTime,
                'status'                 => OrderStatus::Complete,
                'updated_by'             => $updatedBy ?? $order->user_id,
            ]);

            $order->details()->update([
                'status'     => OrderStatus::Complete,
                'updated_by' => $updatedBy ?? $order->user_id,
            ]);

            $this->entitlements->grantOrder($order->refresh(), $updatedBy);

            return $order->refresh();
        });
    }

    public function cancel(string $orderNumber, ?int $updatedBy = null): ?Order
    {
        /** @var class-string<Order> $orderModel */
        $orderModel = config('commerce.models.order', Order::class);

        return DB::transaction(function () use ($orderNumber, $updatedBy, $orderModel): ?Order {
            /** @var Order|null $order */
            $order = $orderModel::query()
                ->with(['details.product', 'invoices'])
                ->where('number', $orderNumber)
                ->lockForUpdate()
                ->first();

            if (! $order instanceof Order) {
                return null;
            }

            if ($order->status !== OrderStatus::Cancelled) {
                $paymentStatus = $order->payment_status === PaymentStatus::Complete
                    ? PaymentStatus::Refunded
                    : PaymentStatus::Cancelled;

                $order->update([
                    'status'         => OrderStatus::Cancelled,
                    'payment_status' => $paymentStatus,
                    'updated_by'     => $updatedBy ?? $order->user_id,
                    'cancel_at'      => now(),
                ]);

                $order->details()->update([
                    'status'     => OrderStatus::Cancelled,
                    'updated_by' => $updatedBy ?? $order->user_id,
                ]);

                $order->invoices()
                    ->where('status', InvoiceStatus::Complete)
                    ->update([
                        'status'     => InvoiceStatus::Cancelled,
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
    public function detailsGroupedByTax(Order $order): array
    {
        $groups = [];
        $order->loadMissing(['details.product']);

        foreach ($order->details as $detail) {
            if (! $detail instanceof OrderDetail || ! $detail->product instanceof Model) {
                continue;
            }

            $tax = (int) ($detail->product->tax ?? 1);
            $groups[$tax][] = $detail->toArray();
        }

        return $groups;
    }
}
