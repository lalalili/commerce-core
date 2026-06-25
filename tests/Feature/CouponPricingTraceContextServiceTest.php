<?php

use Lalalili\CommerceCore\Services\CouponPricingTraceContextService;
use Lalalili\CommerceCore\Tests\Support\TraceTestCart;

class CouponPricingTraceContextServiceTestCoupon
{
    public function __construct(
        public mixed $id = 123,
        public mixed $code = 'COUPON10',
        public mixed $scope = 'all',
        public mixed $limit_qty = 5,
        public mixed $left_qty = 4,
    ) {}

    public function getKey(): int
    {
        return (int) $this->id;
    }
}

it('builds validation, apply, and lifecycle trace payloads from coupon-like models', function (): void {
    $service = app(CouponPricingTraceContextService::class);
    $coupon = new CouponPricingTraceContextServiceTestCoupon;

    expect($service->validationEntry(
        kind: 'member',
        code: 'INPUT10',
        coupon: $coupon,
        status: 'failed',
        amount: '100',
        finalTotal: 900.0,
        reasonCode: 'COUPON_EXPIRED',
        reason: 'Expired',
    ))->toMatchArray([
        'stage' => 'coupon_validate',
        'source' => 'coupon',
        'kind' => 'member',
        'code' => 'COUPON10',
        'id' => 123,
        'scope' => 'all',
        'status' => 'failed',
        'amount' => '100',
        'finalTotal' => 900.0,
        'reasonCode' => 'COUPON_EXPIRED',
        'reason' => 'Expired',
        'metadata' => ['coupon_kind' => 'member'],
    ]);

    expect($service->applyEntry(
        kind: 'promotion',
        code: 'PROMO10',
        coupon: $coupon,
        discount: 50.0,
        finalTotal: 950.0,
    ))->toMatchArray([
        'stage' => 'coupon_apply',
        'kind' => 'promotion',
        'code' => 'COUPON10',
        'status' => 'applied',
        'amount' => 50.0,
        'finalTotal' => 950.0,
    ]);

    expect($service->lifecycleEntry(
        stage: 'coupon_inventory',
        status: 'applied',
        kind: 'promotion',
        coupon: $coupon,
    ))->toMatchArray([
        'stage' => 'coupon_inventory',
        'kind' => 'promotion',
        'code' => 'COUPON10',
        'status' => 'applied',
        'metadata' => [
            'coupon_kind' => 'promotion',
            'limit_qty' => 5,
            'left_qty' => 4,
        ],
    ]);
});

it('normalizes trace arrays and returns the first entry', function (): void {
    $service = app(CouponPricingTraceContextService::class);
    $trace = [
        ['stage' => 'coupon_validate', 'code' => 'A'],
        ['stage' => 'coupon_apply', 'code' => 'A', 0 => 'drop'],
        ['ignored'],
    ];

    expect($service->normalizeEntries($trace))->toBe([
        ['stage' => 'coupon_validate', 'code' => 'A'],
        ['stage' => 'coupon_apply', 'code' => 'A'],
    ])->and($service->firstEntryArray($trace))->toBe([
        'stage' => 'coupon_validate',
        'code' => 'A',
    ])->and($service->firstEntryArray(null))->toBe([]);
});

it('appends and clears coupon trace entries on cart context', function (): void {
    $cart = new TraceTestCart(['pricing_trace' => ['coupon' => [['stage' => 'coupon_apply', 'kind' => 'member', 'code' => 'KEEP']]]]);
    $service = app(CouponPricingTraceContextService::class);

    $service->appendCouponTrace($cart, [
        'stage' => 'coupon_apply',
        'source' => 'coupon',
        'kind' => 'promotion',
        'code' => 'PROMO',
    ]);

    expect(data_get($cart->getContext()->get('pricing_trace'), 'coupon'))->toHaveCount(2);

    $service->clearCouponTrace($cart, 'promotion', 'PROMO');

    expect(data_get($cart->getContext()->get('pricing_trace'), 'coupon'))->toBe([
        ['stage' => 'coupon_apply', 'kind' => 'member', 'code' => 'KEEP'],
    ]);

    $service->clearPricingTrace($cart);

    expect($cart->getContext()->get('pricing_trace'))->toBe([]);
});
