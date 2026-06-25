<?php

use Lalalili\CommerceCore\Services\CouponInventoryService;

class CouponInventoryServiceTestCoupon
{
    public function __construct(
        public int|string|null $limit_qty = 10,
        public int|string|null $left_qty = 5,
    ) {}
}

it('skips inventory callbacks for unlimited coupons', function (): void {
    $service = app(CouponInventoryService::class);

    $called = false;
    $reserved = $service->reserve(
        coupon: new CouponInventoryServiceTestCoupon(limit_qty: null),
        decrement: function () use (&$called): int {
            $called = true;

            return 1;
        },
    );

    expect($reserved)->toBeTrue()
        ->and($called)->toBeFalse();
});

it('reserves limited coupon inventory through host callbacks', function (mixed $decrementResult, bool $expected): void {
    $reserved = app(CouponInventoryService::class)->reserve(
        coupon: new CouponInventoryServiceTestCoupon,
        decrement: static fn (): mixed => $decrementResult,
    );

    expect($reserved)->toBe($expected);
})->with([
    'affected rows' => [1, true],
    'no affected rows' => [0, false],
    'truthy host result' => [true, true],
    'falsy host result' => [false, false],
]);

it('detects whether coupon inventory can be restored', function (): void {
    $service = app(CouponInventoryService::class);

    expect($service->canRestore(new CouponInventoryServiceTestCoupon(limit_qty: 10, left_qty: 9)))->toBeTrue()
        ->and($service->canRestore(new CouponInventoryServiceTestCoupon(limit_qty: 10, left_qty: 10)))->toBeFalse()
        ->and($service->canRestore(new CouponInventoryServiceTestCoupon(limit_qty: null, left_qty: 0)))->toBeFalse()
        ->and($service->canRestore(new stdClass))->toBeFalse();
});

it('restores coupon inventory through host callbacks and touches successful restores', function (): void {
    $service = app(CouponInventoryService::class);
    $coupon = new CouponInventoryServiceTestCoupon(limit_qty: 10, left_qty: 9);
    $touched = false;

    $restored = $service->restore(
        coupon: $coupon,
        increment: static fn (): int => 1,
        touch: function (CouponInventoryServiceTestCoupon $coupon) use (&$touched): void {
            $touched = $coupon->left_qty === 9;
        },
    );

    expect($restored)->toBeTrue()
        ->and($touched)->toBeTrue();
});

it('does not touch failed restores', function (): void {
    $touched = false;

    $restored = app(CouponInventoryService::class)->restore(
        coupon: new CouponInventoryServiceTestCoupon(limit_qty: 10, left_qty: 9),
        increment: static fn (): int => 0,
        touch: function () use (&$touched): void {
            $touched = true;
        },
    );

    expect($restored)->toBeFalse()
        ->and($touched)->toBeFalse();
});
