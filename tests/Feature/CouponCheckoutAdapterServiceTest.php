<?php

use Lalalili\CommerceCore\Services\CouponCheckoutAdapterService;

class CouponCheckoutAdapterServiceTestCart
{
    public function __construct(private readonly mixed $total = 0) {}

    public function getTotal(bool $formatted = true): mixed
    {
        return $this->total;
    }
}

it('asserts the expected checkout cart class', function (): void {
    $service = app(CouponCheckoutAdapterService::class);
    $cart = new CouponCheckoutAdapterServiceTestCart;

    expect($service->assertCheckoutCart($cart, CouponCheckoutAdapterServiceTestCart::class))->toBe($cart);

    $service->assertCheckoutCart(new stdClass, CouponCheckoutAdapterServiceTestCart::class, 'Invalid host cart.');
})->throws(InvalidArgumentException::class, 'Invalid host cart.');

it('normalizes host coupon responses into checkout results', function (): void {
    $service = app(CouponCheckoutAdapterService::class);

    $result = $service->resultFromResponse([
        'success' => 1,
        'message' => 'applied',
        'data' => ['discount' => 100],
    ]);

    expect($result->successful)->toBeTrue()
        ->and($result->message)->toBe('applied')
        ->and($result->data)->toBe(['discount' => 100])
        ->and($service->resultFromResponse(['data' => 'invalid'])->toArray())->toBe([
            'success' => false,
            'message' => '',
            'data' => [],
        ]);
});

it('resolves order totals from context, cart, or fallback callbacks', function (): void {
    $service = app(CouponCheckoutAdapterService::class);

    expect($service->orderTotal(new CouponCheckoutAdapterServiceTestCart(500), ['order_total' => '300.5']))->toBe(300.5)
        ->and($service->orderTotal(new CouponCheckoutAdapterServiceTestCart('450')))->toBe(450.0)
        ->and($service->orderTotal(new stdClass, fallback: static fn (): int => 250))->toBe(250.0)
        ->and($service->orderTotal(new stdClass))->toBe(0.0);
});
