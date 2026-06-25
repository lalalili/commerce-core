<?php

use Lalalili\CommerceCore\Services\CouponPricingTraceService;

it('appends coupon trace entries and keeps latest entry by identity', function (): void {
    $service = app(CouponPricingTraceService::class);

    $pricingTrace = $service->appendCouponTrace([
        'shipping' => [
            ['stage' => 'shipping_quote', 'status' => 'selected'],
        ],
        'coupon' => [
            [
                'stage' => 'coupon_apply',
                'source' => 'coupon',
                'kind' => 'member',
                'code' => 'WELCOME',
                'amount' => 50,
            ],
        ],
    ], [
        'stage' => 'coupon_apply',
        'source' => 'coupon',
        'kind' => 'member',
        'code' => 'WELCOME',
        'amount' => 80,
    ]);

    expect($pricingTrace['shipping'])->toBe([
        ['stage' => 'shipping_quote', 'status' => 'selected'],
    ])
        ->and($pricingTrace['coupon'])->toHaveCount(1)
        ->and($pricingTrace['coupon'][0]['amount'])->toBe(80);
});

it('limits coupon trace entries after merging', function (): void {
    $service = app(CouponPricingTraceService::class);

    $pricingTrace = $service->appendCouponTrace([
        'coupon' => [
            ['stage' => 'coupon_apply', 'source' => 'coupon', 'kind' => 'member', 'code' => 'A'],
            ['stage' => 'coupon_apply', 'source' => 'coupon', 'kind' => 'member', 'code' => 'B'],
        ],
    ], [
        ['stage' => 'coupon_apply', 'source' => 'coupon', 'kind' => 'member', 'code' => 'C'],
    ], maxEntries: 2);

    expect(array_column($pricingTrace['coupon'], 'code'))->toBe(['B', 'C']);
});

it('clears coupon trace entries by kind and code', function (): void {
    $service = app(CouponPricingTraceService::class);

    $pricingTrace = [
        'coupon' => [
            ['stage' => 'coupon_apply', 'source' => 'coupon', 'kind' => 'member', 'code' => 'A'],
            ['stage' => 'coupon_apply', 'source' => 'coupon', 'kind' => 'promotion', 'code' => 'A'],
            ['stage' => 'coupon_apply', 'source' => 'coupon', 'kind' => 'promotion', 'code' => 'B'],
        ],
    ];

    $afterKindClear = $service->clearCouponTrace($pricingTrace, kind: 'member');
    $afterCodeClear = $service->clearCouponTrace($pricingTrace, code: 'A');
    $afterAllClear = $service->clearCouponTrace($pricingTrace);

    expect(array_column($afterKindClear['coupon'], 'kind'))->toBe(['promotion', 'promotion'])
        ->and(array_column($afterCodeClear['coupon'], 'code'))->toBe(['B'])
        ->and($afterAllClear)->not->toHaveKey('coupon');
});

it('normalizes only string-keyed trace entries', function (): void {
    $service = app(CouponPricingTraceService::class);

    expect($service->normalizeEntries([
        'stage' => 'coupon_apply',
        0 => 'ignored',
    ]))->toBe([
        ['stage' => 'coupon_apply'],
    ])
        ->and($service->normalizeEntries([
            ['stage' => 'coupon_apply', 0 => 'ignored'],
            'ignored',
        ]))->toBe([
            ['stage' => 'coupon_apply'],
        ]);
});
