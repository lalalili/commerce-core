<?php

use Lalalili\CommerceCore\Services\CouponCartConditionPayloadBuilder;

it('builds reusable member and promotion coupon cart condition payloads', function (
    string $kind,
    string $name,
    int|float $discount,
    string $expectedType,
    int $expectedOrder,
): void {
    $service = app(CouponCartConditionPayloadBuilder::class);
    $trace = [
        'stage' => 'coupon_apply',
        'kind' => $kind,
        'code' => 'COUPON10',
    ];

    expect($service->payload($kind, $discount, $trace, $name))->toBe([
        'name' => $name,
        'type' => $expectedType,
        'target' => 'total',
        'value' => -1 * $discount,
        'order' => $expectedOrder,
        'attributes' => [
            'pricing_trace_entry' => $trace,
        ],
    ])->and($service->typeFor($kind))->toBe($expectedType)
        ->and($service->orderFor($kind))->toBe($expectedOrder);
})->with([
    'member coupon' => [
        'kind' => 'member',
        'name' => '會員折扣券',
        'discount' => 100,
        'expectedType' => 'member_coupon',
        'expectedOrder' => CouponCartConditionPayloadBuilder::MEMBER_COUPON_CONDITION_ORDER,
    ],
    'promotion coupon' => [
        'kind' => 'promotion',
        'name' => '活動折扣券',
        'discount' => 75.5,
        'expectedType' => 'promotion_coupon',
        'expectedOrder' => CouponCartConditionPayloadBuilder::PROMOTION_COUPON_CONDITION_ORDER,
    ],
]);

it('rejects unsupported coupon kinds', function (): void {
    app(CouponCartConditionPayloadBuilder::class)->typeFor('unknown');
})->throws(InvalidArgumentException::class, 'Unsupported coupon kind [unknown].');
