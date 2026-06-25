<?php

use Illuminate\Support\Collection;
use Lalalili\CommerceCore\Services\CheckoutCartCompletionService;

it('extracts checkout item ids from item payloads or collection keys', function (): void {
    $service = app(CheckoutCartCompletionService::class);

    expect($service->itemIds([
        (object) ['id' => 123],
        ['id' => 'SKU-2'],
        (object) ['id' => null],
        (object) ['name' => 'fallback'],
    ]))->toBe(['123', 'SKU-2', '3'])
        ->and($service->itemIds(new Collection([
            'SKU-1' => (object) ['id' => 999],
            'SKU-2' => (object) ['id' => 888],
        ]), preferKeys: true))->toBe(['SKU-1', 'SKU-2'])
        ->and($service->itemIds(null))->toBe([]);
});

it('removes checked out items before clearing checkout by default', function (): void {
    $service = app(CheckoutCartCompletionService::class);
    $events = [];

    $itemIds = $service->complete(
        checkoutContent: [
            (object) ['id' => 'A'],
            (object) ['id' => 'B'],
        ],
        removeCartItem: function (string $itemId) use (&$events): void {
            $events[] = "remove:{$itemId}";
        },
        clearCheckout: function () use (&$events): void {
            $events[] = 'clear';
        },
    );

    expect($itemIds)->toBe(['A', 'B'])
        ->and($events)->toBe(['remove:A', 'remove:B', 'clear']);
});

it('can clear checkout before removing keyed cart items', function (): void {
    $service = app(CheckoutCartCompletionService::class);
    $events = [];

    $itemIds = $service->complete(
        checkoutContent: new Collection([
            'A' => (object) ['id' => 1],
            'B' => (object) ['id' => 2],
        ]),
        removeCartItem: function (string $itemId) use (&$events): void {
            $events[] = "remove:{$itemId}";
        },
        clearCheckout: function () use (&$events): void {
            $events[] = 'clear';
        },
        clearCheckoutFirst: true,
        preferKeys: true,
    );

    expect($itemIds)->toBe(['A', 'B'])
        ->and($events)->toBe(['clear', 'remove:A', 'remove:B']);
});
