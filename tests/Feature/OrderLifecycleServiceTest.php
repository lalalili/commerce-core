<?php

use Lalalili\CommerceCore\Enums\InvoiceStatus;
use Lalalili\CommerceCore\Enums\OrderStatus;
use Lalalili\CommerceCore\Enums\PaymentStatus;
use Lalalili\CommerceCore\Models\Order;
use Lalalili\CommerceCore\Models\OrderInvoice;
use Lalalili\CommerceCore\Models\Product;
use Lalalili\CommerceCore\Models\ProductUser;
use Lalalili\CommerceCore\Services\OrderLifecycleService;

it('creates orders from products and groups details by tax', function (): void {
    $service = app(OrderLifecycleService::class);
    $product = Product::query()->create([
        'title'       => 'Cloudflare Stream course',
        'type'        => 1,
        'list_price'  => 1200,
        'sales_price' => 900,
        'tax'         => 1,
    ]);

    $order = $service->create(1, [
        ['product_id' => $product->id, 'qty' => 2],
    ], [
        'number' => '260510ABCD',
    ]);

    expect($order->number)->toBe('260510ABCD')
        ->and($order->total_sales_price)->toBe(1800)
        ->and($order->total_discount_amt)->toBe(600)
        ->and($order->details)->toHaveCount(1)
        ->and($service->detailsGroupedByTax($order))->toHaveKey(1);
});

it('rejects creating orders without items', function (): void {
    $service = app(OrderLifecycleService::class);

    expect(fn () => $service->create(1, [], ['number' => '260510NONE']))
        ->toThrow(InvalidArgumentException::class, 'Order items must not be empty.');

    expect(Order::query()->where('number', '260510NONE')->exists())->toBeFalse();
});

it('marks paid orders idempotently and grants entitlements', function (): void {
    $service = app(OrderLifecycleService::class);
    $product = Product::query()->create([
        'title'       => 'Paid course',
        'type'        => 1,
        'list_price'  => 1000,
        'sales_price' => 1000,
        'tax'         => 1,
    ]);
    $order = $service->create(1, [['product_id' => $product->id]], ['number' => '260510PAID']);

    $paid = $service->markPaid($order->number, 'Succeeded', now());
    $service->markPaid($order->number, 'Succeeded again', now());

    expect($paid?->payment_status)->toBe(PaymentStatus::Complete)
        ->and($paid?->status)->toBe(OrderStatus::Complete)
        ->and(ProductUser::query()->where('product_id', $product->id)->where('user_id', 1)->count())->toBe(1);
});

it('cancels paid orders as refunded and revokes entitlements', function (): void {
    $service = app(OrderLifecycleService::class);
    $product = Product::query()->create([
        'title'       => 'Refunded course',
        'type'        => 1,
        'list_price'  => 1000,
        'sales_price' => 1000,
        'tax'         => 1,
    ]);
    $order = $service->create(1, [['product_id' => $product->id]], ['number' => '260510RFND']);
    $service->markPaid($order->number, 'Succeeded', now());

    OrderInvoice::query()->create([
        'user_id'           => 1,
        'order_id'          => $order->id,
        'order_number'      => $order->number,
        'total_sales_price' => 1000,
        'type'              => 1,
        'number'            => 'AB12345678',
        'status'            => InvoiceStatus::Complete,
    ]);

    $cancelled = $service->cancel($order->number);

    expect($cancelled?->status)->toBe(OrderStatus::Cancelled)
        ->and($cancelled?->payment_status)->toBe(PaymentStatus::Refunded)
        ->and(ProductUser::query()->where('product_id', $product->id)->where('user_id', 1)->exists())->toBeFalse()
        ->and(OrderInvoice::query()->where('order_number', $order->number)->first()?->status)->toBe(InvoiceStatus::Cancelled);
});
