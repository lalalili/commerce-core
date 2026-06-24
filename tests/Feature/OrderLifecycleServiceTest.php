<?php

use Lalalili\CommerceCore\Enums\InvoiceStatus;
use Lalalili\CommerceCore\Enums\OrderStatus;
use Lalalili\CommerceCore\Enums\PaymentStatus;
use Lalalili\CommerceCore\Events\OrderFinished;
use Lalalili\CommerceCore\Events\OrderShipped;
use Lalalili\CommerceCore\Models\Order;
use Lalalili\CommerceCore\Models\OrderInvoice;
use Lalalili\CommerceCore\Models\Product;
use Lalalili\CommerceCore\Models\ProductUser;
use Lalalili\CommerceCore\Services\OrderLifecycleService;
use Lalalili\CommerceCore\Tests\Support\RecordingLifecycleHook;

it('creates orders from products and groups details by tax', function (): void {
    $service = app(OrderLifecycleService::class);
    $product = Product::query()->create([
        'title' => 'Cloudflare Stream course',
        'type' => 1,
        'list_price' => 1200,
        'sales_price' => 900,
        'tax' => 1,
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

it('rejects creating orders when a product cannot be normalized', function (): void {
    $service = app(OrderLifecycleService::class);

    expect(fn () => $service->create(1, [
        ['product_id' => 'missing-product'],
    ], ['number' => '260510MISS']))
        ->toThrow(InvalidArgumentException::class, 'Product [missing-product] does not exist.');

    expect(Order::query()->where('number', '260510MISS')->exists())->toBeFalse();
});

it('uses explicit checkout item attributes when creating order details', function (): void {
    $service = app(OrderLifecycleService::class);
    $product = Product::query()->create([
        'title' => 'Original product title',
        'type' => 1,
        'list_price' => 1200,
        'sales_price' => 900,
        'tax' => 1,
    ]);

    $order = $service->create(1, [
        [
            'product_id' => $product->id,
            'qty' => 1,
            'title' => 'Checkout title',
            'list_price' => 1500,
            'sales_price' => 1000,
            'product_type' => 9,
            'company_id' => 20,
        ],
    ], [
        'number' => '260510ITEM',
    ]);

    $detail = $order->details()->first();

    expect($order->total_sales_price)->toBe(1000)
        ->and($order->total_discount_amt)->toBe(500)
        ->and($detail?->title)->toBe('Checkout title')
        ->and($detail?->list_price)->toBe(1500)
        ->and($detail?->sales_price)->toBe(1000)
        ->and($detail?->product_type)->toBe(9)
        ->and($detail?->company_id)->toBe(20);
});

it('marks paid orders idempotently and grants entitlements', function (): void {
    $service = app(OrderLifecycleService::class);
    $product = Product::query()->create([
        'title' => 'Paid course',
        'type' => 1,
        'list_price' => 1000,
        'sales_price' => 1000,
        'tax' => 1,
    ]);
    $order = $service->create(1, [['product_id' => $product->id]], ['number' => '260510PAID']);

    $paid = $service->markPaid($order->number, 'Succeeded', now());
    $service->markPaid($order->number, 'Succeeded again', now());

    expect($paid?->payment_status)->toBe(PaymentStatus::Complete)
        ->and($paid?->status)->toBe(OrderStatus::Complete)
        ->and(ProductUser::query()->where('product_id', $product->id)->where('user_id', 1)->count())->toBe(1);
});

it('accepts string lifecycle actors for host updated by columns', function (): void {
    $service = app(OrderLifecycleService::class);
    $product = Product::query()->create([
        'title' => 'String actor course',
        'type' => 1,
        'list_price' => 1000,
        'sales_price' => 1000,
        'tax' => 1,
    ]);
    $order = $service->create(1, [['product_id' => $product->id]], ['number' => '260510ACTR']);

    $paid = $service->markPaid($order->number, 'Succeeded', now(), 'paidOrder');

    expect($paid?->updated_by)->toBe('paidOrder')
        ->and($paid?->details()->sole()->updated_by)->toBe('paidOrder');
});

it('dispatches paid lifecycle hooks once when marking an order paid', function (): void {
    RecordingLifecycleHook::reset();
    config()->set('commerce.lifecycle.hooks', [RecordingLifecycleHook::class]);

    $service = app(OrderLifecycleService::class);
    $product = Product::query()->create([
        'title' => 'Hooked paid course',
        'type' => 1,
        'list_price' => 1000,
        'sales_price' => 1000,
        'tax' => 1,
    ]);
    $order = $service->create(1, [['product_id' => $product->id]], ['number' => '260510HOOK']);

    $service->markPaid($order->number, 'Succeeded', now());
    $service->markPaid($order->number, 'Succeeded again', now());

    expect(RecordingLifecycleHook::$events)->toBe([
        ['event' => 'paid', 'order_number' => '260510HOOK'],
    ]);
});

it('cancels paid orders as refunded and revokes entitlements', function (): void {
    $service = app(OrderLifecycleService::class);
    $product = Product::query()->create([
        'title' => 'Refunded course',
        'type' => 1,
        'list_price' => 1000,
        'sales_price' => 1000,
        'tax' => 1,
    ]);
    $order = $service->create(1, [['product_id' => $product->id]], ['number' => '260510RFND']);
    $service->markPaid($order->number, 'Succeeded', now());

    OrderInvoice::query()->create([
        'user_id' => 1,
        'order_id' => $order->id,
        'order_number' => $order->number,
        'total_sales_price' => 1000,
        'type' => 1,
        'number' => 'AB12345678',
        'status' => InvoiceStatus::Complete,
    ]);

    $cancelled = $service->cancel($order->number);

    expect($cancelled?->status)->toBe(OrderStatus::Cancelled)
        ->and($cancelled?->payment_status)->toBe(PaymentStatus::Refunded)
        ->and(ProductUser::query()->where('product_id', $product->id)->where('user_id', 1)->exists())->toBeFalse()
        ->and(OrderInvoice::query()->where('order_number', $order->number)->first()?->status)->toBe(InvoiceStatus::Cancelled);
});

it('marks paid orders as refunded without cancelling or revoking entitlements', function (): void {
    $service = app(OrderLifecycleService::class);
    $product = Product::query()->create([
        'title' => 'Standalone refunded course',
        'type' => 1,
        'list_price' => 1000,
        'sales_price' => 1000,
        'tax' => 1,
    ]);
    $order = $service->create(1, [['product_id' => $product->id]], ['number' => '260510MRFD']);
    $service->markPaid($order->number, 'Succeeded', now());

    $refunded = $service->markRefunded($order->number, 'Refunded');

    expect($refunded?->status)->toBe(OrderStatus::Complete)
        ->and($refunded?->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($refunded?->payment_status_message)->toBe('Refunded')
        ->and(ProductUser::query()->where('product_id', $product->id)->where('user_id', 1)->exists())->toBeTrue();
});

it('dispatches cancelled and refunded hooks only for the first paid cancellation', function (): void {
    RecordingLifecycleHook::reset();
    config()->set('commerce.lifecycle.hooks', [RecordingLifecycleHook::class]);

    $service = app(OrderLifecycleService::class);
    $product = Product::query()->create([
        'title' => 'Hooked refunded course',
        'type' => 1,
        'list_price' => 1000,
        'sales_price' => 1000,
        'tax' => 1,
    ]);
    $order = $service->create(1, [['product_id' => $product->id]], ['number' => '260510CANC']);
    $service->markPaid($order->number, 'Succeeded', now());
    RecordingLifecycleHook::reset();

    $service->cancel($order->number);
    $service->cancel($order->number);

    expect(RecordingLifecycleHook::$events)->toBe([
        ['event' => 'cancelled', 'order_number' => '260510CANC'],
        ['event' => 'refunded', 'order_number' => '260510CANC'],
    ]);
});

it('dispatches refunded hooks once when marking an order refunded', function (): void {
    RecordingLifecycleHook::reset();
    config()->set('commerce.lifecycle.hooks', [RecordingLifecycleHook::class]);

    $service = app(OrderLifecycleService::class);
    $product = Product::query()->create([
        'title' => 'Hooked standalone refunded course',
        'type' => 1,
        'list_price' => 1000,
        'sales_price' => 1000,
        'tax' => 1,
    ]);
    $order = $service->create(1, [['product_id' => $product->id]], ['number' => '260510RFHK']);
    $service->markPaid($order->number, 'Succeeded', now());
    RecordingLifecycleHook::reset();

    $service->markRefunded($order->number, 'Refunded');
    $service->markRefunded($order->number, 'Refunded again');

    expect(RecordingLifecycleHook::$events)->toBe([
        ['event' => 'refunded', 'order_number' => '260510RFHK'],
    ]);
});

it('does not dispatch refunded hooks for unpaid cancellations', function (): void {
    RecordingLifecycleHook::reset();
    config()->set('commerce.lifecycle.hooks', [RecordingLifecycleHook::class]);

    $service = app(OrderLifecycleService::class);
    $product = Product::query()->create([
        'title' => 'Hooked cancelled course',
        'type' => 1,
        'list_price' => 1000,
        'sales_price' => 1000,
        'tax' => 1,
    ]);
    $order = $service->create(1, [['product_id' => $product->id]], ['number' => '260510VOID']);

    $service->cancel($order->number);

    expect(RecordingLifecycleHook::$events)->toBe([
        ['event' => 'cancelled', 'order_number' => '260510VOID'],
    ]);
});

it('marks orders as shipped idempotently and dispatches fulfillment hooks once', function (): void {
    RecordingLifecycleHook::reset();
    Event::fake([OrderShipped::class]);
    config()->set('commerce.lifecycle.hooks', [RecordingLifecycleHook::class]);

    $service = app(OrderLifecycleService::class);
    $product = Product::query()->create([
        'title' => 'Shipped course',
        'type' => 1,
        'list_price' => 1000,
        'sales_price' => 1000,
        'tax' => 1,
    ]);
    $order = $service->create(1, [['product_id' => $product->id]], ['number' => '260510SHIP']);
    $service->markPaid($order->number, 'Succeeded', now());
    RecordingLifecycleHook::reset();

    $shipped = $service->markShipped($order->number, 9);
    $service->markShipped($order->number, 9);

    expect($shipped?->status)->toBe(OrderStatus::Shipping)
        ->and($shipped?->details()->pluck('status')->unique()->values()->all())->toBe([OrderStatus::Shipping]);

    Event::assertDispatched(OrderShipped::class, 1);
    expect(RecordingLifecycleHook::$events)->toBe([
        ['event' => 'shipped', 'order_number' => '260510SHIP'],
    ]);
});

it('marks orders as finished idempotently and dispatches fulfillment hooks once', function (): void {
    RecordingLifecycleHook::reset();
    Event::fake([OrderFinished::class]);
    config()->set('commerce.lifecycle.hooks', [RecordingLifecycleHook::class]);

    $service = app(OrderLifecycleService::class);
    $product = Product::query()->create([
        'title' => 'Finished course',
        'type' => 1,
        'list_price' => 1000,
        'sales_price' => 1000,
        'tax' => 1,
    ]);
    $order = $service->create(1, [['product_id' => $product->id]], ['number' => '260510DONE']);

    $finished = $service->markFinished($order->number, 9);
    $service->markFinished($order->number, 9);

    expect($finished?->status)->toBe(OrderStatus::Finished)
        ->and($finished?->payment_status)->toBe(PaymentStatus::Complete)
        ->and($finished?->details()->pluck('status')->unique()->values()->all())->toBe([OrderStatus::Finished]);

    Event::assertDispatched(OrderFinished::class, 1);
    expect(RecordingLifecycleHook::$events)->toBe([
        ['event' => 'finished', 'order_number' => '260510DONE'],
    ]);
});
