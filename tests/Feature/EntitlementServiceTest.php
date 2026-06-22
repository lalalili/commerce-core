<?php

use Lalalili\CommerceCore\Models\Order;
use Lalalili\CommerceCore\Models\OrderDetail;
use Lalalili\CommerceCore\Models\Product;
use Lalalili\CommerceCore\Models\ProductUser;
use Lalalili\CommerceCore\Services\EntitlementService;

/**
 * @return array{0: Order, 1: Product}
 */
function seedEntitlementOrder(int $userId, string $orderNumber): array
{
    $product = Product::query()->create([
        'title' => 'Grant course',
        'type' => 1,
        'list_price' => 1000,
        'sales_price' => 800,
        'tax' => 1,
    ]);

    /** @var Order $order */
    $order = Order::query()->create([
        'number' => $orderNumber,
        'user_id' => $userId,
        'total_sales_price' => 800,
    ]);

    OrderDetail::query()->create([
        'order_id' => $order->id,
        'order_number' => $order->number,
        'product_id' => $product->id,
        'product_type' => 1,
        'title' => 'Grant course',
        'qty' => 1,
        'list_price' => 1000,
        'sales_price' => 800,
        'status' => 0,
    ]);

    return [$order, $product];
}

it('does not grant entitlements when the feature is disabled', function (): void {
    config()->set('commerce.entitlements.enabled', false);
    [$order] = seedEntitlementOrder(1, '260622DIS0');

    app(EntitlementService::class)->grantOrder($order);

    expect(ProductUser::query()->count())->toBe(0);
});

it('grants entitlements idempotently', function (): void {
    [$order, $product] = seedEntitlementOrder(1, '260622IDEM');

    $service = app(EntitlementService::class);
    $service->grantOrder($order);
    $service->grantOrder($order);

    expect(ProductUser::query()
        ->where('product_id', $product->id)
        ->where('user_id', 1)
        ->count())->toBe(1);
});

it('skips order details whose product cannot be resolved', function (): void {
    /** @var Order $order */
    $order = Order::query()->create([
        'number' => '260622SKIP',
        'user_id' => 1,
        'total_sales_price' => 0,
    ]);

    OrderDetail::query()->create([
        'order_id' => $order->id,
        'order_number' => $order->number,
        'product_id' => '01JZZZZZZZZZZZZZZZZZZZZZZZ', // no such product
        'title' => 'Orphan line',
        'qty' => 1,
        'list_price' => 500,
        'sales_price' => 500,
        'status' => 0,
    ]);

    app(EntitlementService::class)->grantOrder($order);

    expect(ProductUser::query()->count())->toBe(0);
});

it('revokes only the entitlements belonging to the order user', function (): void {
    [$orderA, $product] = seedEntitlementOrder(1, '260622RVKA');

    // A second user owns the same product through their own order.
    /** @var Order $orderB */
    $orderB = Order::query()->create([
        'number' => '260622RVKB',
        'user_id' => 2,
        'total_sales_price' => 800,
    ]);
    OrderDetail::query()->create([
        'order_id' => $orderB->id,
        'order_number' => $orderB->number,
        'product_id' => $product->id,
        'product_type' => 1,
        'title' => 'Grant course',
        'qty' => 1,
        'list_price' => 1000,
        'sales_price' => 800,
        'status' => 0,
    ]);

    $service = app(EntitlementService::class);
    $service->grantOrder($orderA);
    $service->grantOrder($orderB);

    expect(ProductUser::query()->count())->toBe(2);

    $service->revokeOrder($orderA);

    expect(ProductUser::query()->where('user_id', 1)->exists())->toBeFalse()
        ->and(ProductUser::query()->where('user_id', 2)->exists())->toBeTrue();
});
