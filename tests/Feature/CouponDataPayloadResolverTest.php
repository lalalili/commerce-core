<?php

use Lalalili\CommerceCore\Services\CouponDataPayloadResolver;

enum CouponDataPayloadResolverTestScope: int
{
    case All = 1;
}

enum CouponDataPayloadResolverTestStatus: int
{
    case Active = 1;
}

class CouponDataPayloadResolverTestCoupon
{
    public string $code = 'COUPON10';

    public CouponDataPayloadResolverTestScope $scope = CouponDataPayloadResolverTestScope::All;

    public string $trigger_amount = '1000';

    public string $amount = '120.5';

    public string $amount_mode = 'fixed';

    public CouponDataPayloadResolverTestStatus|int $status = CouponDataPayloadResolverTestStatus::Active;

    public ?string $limit_qty = '10';

    public int|string|null $left_qty = 8;

    public ?string $user_id = '99';
}

it('normalizes coupon data payloads from host coupon-like models', function (): void {
    $payload = app(CouponDataPayloadResolver::class)->payload(
        coupon: new CouponDataPayloadResolverTestCoupon,
        attributes: [
            'coupon_id' => 123,
            'title' => 'Welcome',
        ],
    );

    expect($payload)->toBe([
        'code' => 'COUPON10',
        'scope' => 1,
        'trigger_amount' => 1000,
        'amount' => 120.5,
        'amount_mode' => 'fixed',
        'status' => true,
        'limit_qty' => 10,
        'left_qty' => 8,
        'user_id' => 99,
        'attributes' => [
            'coupon_id' => 123,
            'title' => 'Welcome',
        ],
    ]);
});

it('allows hosts to override scope and status for project-specific coupon data semantics', function (): void {
    $payload = app(CouponDataPayloadResolver::class)->payload(
        coupon: tap(new CouponDataPayloadResolverTestCoupon, function (CouponDataPayloadResolverTestCoupon $coupon): void {
            $coupon->status = 0;
            $coupon->limit_qty = null;
            $coupon->left_qty = null;
        }),
        scope: 'all',
        status: true,
    );

    expect($payload)
        ->scope->toBe('all')
        ->status->toBeTrue()
        ->limit_qty->toBeNull()
        ->left_qty->toBeNull();
});

it('uses safe defaults for missing or non numeric coupon fields', function (): void {
    $payload = app(CouponDataPayloadResolver::class)->payload(new stdClass);

    expect($payload)->toBe([
        'code' => '',
        'scope' => '',
        'trigger_amount' => null,
        'amount' => 0.0,
        'amount_mode' => null,
        'status' => false,
        'limit_qty' => null,
        'left_qty' => null,
        'user_id' => null,
        'attributes' => [],
    ]);
});
