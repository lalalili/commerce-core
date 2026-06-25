<?php

use Lalalili\CommerceCore\Services\CouponReasonMessageService;

it('resolves coupon reason messages from host supplied mappings', function (): void {
    $service = new CouponReasonMessageService;

    $messages = [
        'member' => [
            'COUPON_NOT_FOUND' => 'Member coupon not found',
            CouponReasonMessageService::DEFAULT_REASON_KEY => 'No member coupon',
        ],
        'promotion' => [
            'AUTH_REQUIRED' => 'Please sign in',
            CouponReasonMessageService::DEFAULT_REASON_KEY => 'Promotion unavailable',
        ],
    ];

    expect($service->resolve('member', 'COUPON_NOT_FOUND', $messages))->toBe('Member coupon not found')
        ->and($service->resolve('member', 'UNKNOWN', $messages))->toBe('No member coupon')
        ->and($service->resolve('promotion', 'AUTH_REQUIRED', $messages))->toBe('Please sign in')
        ->and($service->resolve('promotion', null, $messages))->toBe('Promotion unavailable')
        ->and($service->resolve('unknown', 'AUTH_REQUIRED', $messages, 'Fallback'))->toBe('Fallback');
});

it('resolves eligibility failure messages with host overrides', function (): void {
    $service = new CouponReasonMessageService;

    expect($service->eligibilityFailure(
        kind: 'promotion',
        reason: '未達使用條件，請檢查折扣金額或使用門檻',
        memberDefault: 'Member default',
        promotionDefault: 'Promotion default',
        promotionReasonOverrides: [
            '未達使用條件，請檢查折扣金額或使用門檻' => '請加購商品後使用',
        ],
    ))->toBe('請加購商品後使用')
        ->and($service->eligibilityFailure('member', 'Custom member reason', 'Member default', 'Promotion default'))
        ->toBe('Custom member reason')
        ->and($service->eligibilityFailure('member', null, 'Member default', 'Promotion default'))
        ->toBe('Member default')
        ->and($service->eligibilityFailure('promotion', '', 'Member default', 'Promotion default'))
        ->toBe('Promotion default');
});
