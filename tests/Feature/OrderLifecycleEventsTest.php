<?php

use Illuminate\Support\Facades\Event;
use Lalalili\CommerceCore\Events\OrderCancelled;
use Lalalili\CommerceCore\Events\OrderCreated;
use Lalalili\CommerceCore\Events\OrderPaid;
use Lalalili\CommerceCore\Models\Product;
use Lalalili\CommerceCore\Services\OrderLifecycleService;

function makeOrderableProduct(string $title): Product
{
    return Product::query()->create([
        'title' => $title,
        'type' => 1,
        'list_price' => 1000,
        'sales_price' => 900,
        'tax' => 1,
    ]);
}

it('dispatches OrderCreated after creating an order', function (): void {
    Event::fake([OrderCreated::class, OrderPaid::class, OrderCancelled::class]);
    $service = app(OrderLifecycleService::class);
    $product = makeOrderableProduct('Created course');

    $order = $service->create(1, [['product_id' => $product->id]], ['number' => '260622CRT']);

    Event::assertDispatched(OrderCreated::class, fn (OrderCreated $event): bool => $event->order->is($order));
    Event::assertNotDispatched(OrderPaid::class);
});

it('dispatches OrderPaid once on transition and not on idempotent re-mark', function (): void {
    Event::fake([OrderPaid::class]);
    $service = app(OrderLifecycleService::class);
    $product = makeOrderableProduct('Paid course');
    $order = $service->create(1, [['product_id' => $product->id]], ['number' => '260622PAY']);

    $service->markPaid($order->number, 'Succeeded', now());
    $service->markPaid($order->number, 'Succeeded again', now());

    Event::assertDispatchedTimes(OrderPaid::class, 1);
    Event::assertDispatched(OrderPaid::class, fn (OrderPaid $event): bool => $event->order->is($order)
        && $event->paymentStatusMessage === 'Succeeded');
});

it('dispatches OrderCancelled once on transition and not when already cancelled', function (): void {
    Event::fake([OrderCancelled::class]);
    $service = app(OrderLifecycleService::class);
    $product = makeOrderableProduct('Cancelled course');
    $order = $service->create(1, [['product_id' => $product->id]], ['number' => '260622CAN']);
    $service->markPaid($order->number, 'Succeeded', now());

    $service->cancel($order->number);
    $service->cancel($order->number);

    Event::assertDispatchedTimes(OrderCancelled::class, 1);
    Event::assertDispatched(OrderCancelled::class, fn (OrderCancelled $event): bool => $event->order->is($order));
});

it('does not dispatch OrderPaid when the order number is unknown', function (): void {
    Event::fake([OrderPaid::class]);
    $service = app(OrderLifecycleService::class);

    expect($service->markPaid('260622MISS', 'Succeeded', now()))->toBeNull();

    Event::assertNotDispatched(OrderPaid::class);
});
