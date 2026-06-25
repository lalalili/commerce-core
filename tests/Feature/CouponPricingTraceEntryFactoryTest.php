<?php

use Lalalili\CommerceCore\Services\CouponPricingTraceEntryFactory;

it('builds coupon validation entry payloads', function (): void {
    $payload = (new CouponPricingTraceEntryFactory)->validation(
        kind: 'member',
        code: 'INPUT10',
        couponCode: 'COUPON10',
        couponId: 123,
        scope: 'all',
        status: 'skipped',
        amount: '10',
        finalTotal: 990.0,
        reasonCode: 'expired',
        reason: 'Expired',
    );

    expect($payload)->toMatchArray([
        'stage' => 'coupon_validate',
        'source' => 'coupon',
        'status' => 'skipped',
        'scope' => 'all',
        'kind' => 'member',
        'code' => 'COUPON10',
        'id' => 123,
        'amount' => '10',
        'finalTotal' => 990.0,
        'reasonCode' => 'expired',
        'reason' => 'Expired',
        'metadata' => ['coupon_kind' => 'member'],
    ]);
});

it('builds coupon apply entry payloads and derives status from discount', function (): void {
    $factory = new CouponPricingTraceEntryFactory;

    expect($factory->apply('promotion', 'PROMO', null, null, '', 50.0, 950.0))
        ->toMatchArray([
            'stage' => 'coupon_apply',
            'status' => 'applied',
            'code' => 'PROMO',
            'amount' => 50.0,
            'finalTotal' => 950.0,
        ])
        ->and($factory->apply('promotion', 'PROMO', null, null, '', 0.0, 1000.0))
        ->toMatchArray([
            'status' => 'skipped',
            'amount' => 0.0,
        ]);
});

it('builds coupon lifecycle entry payloads with inventory metadata', function (): void {
    $payload = (new CouponPricingTraceEntryFactory)->lifecycle(
        stage: 'coupon_redeem',
        status: 'applied',
        kind: 'member',
        couponCode: 'MEMBER10',
        couponId: 'abc',
        scope: 'member',
        limitQuantity: 10,
        leftQuantity: 9,
    );

    expect($payload)->toMatchArray([
        'stage' => 'coupon_redeem',
        'source' => 'coupon',
        'status' => 'applied',
        'scope' => 'member',
        'kind' => 'member',
        'code' => 'MEMBER10',
        'id' => 'abc',
        'metadata' => [
            'coupon_kind' => 'member',
            'limit_qty' => 10,
            'left_qty' => 9,
        ],
    ]);
});
