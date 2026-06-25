<?php

use Lalalili\CommerceCore\Services\CouponTracePayloadResolver;

enum CouponTracePayloadResolverTestScope: string
{
    case All = 'all';
}

class CouponTracePayloadResolverTestCoupon
{
    public function __construct(
        public mixed $id = 123,
        public mixed $code = 'COUPON10',
        public mixed $scope = CouponTracePayloadResolverTestScope::All,
        public mixed $limit_qty = 10,
        public mixed $left_qty = 8,
        private readonly mixed $key = 'coupon-key',
    ) {}

    public function getKey(): mixed
    {
        return $this->key;
    }
}

it('resolves coupon trace payloads from host coupon-like models', function (): void {
    $resolver = app(CouponTracePayloadResolver::class);

    expect($resolver->payload(new CouponTracePayloadResolverTestCoupon))->toBe([
        'code' => 'COUPON10',
        'id' => 'coupon-key',
        'scope' => 'all',
        'limit_qty' => 10,
        'left_qty' => 8,
    ]);
});

it('falls back to scalar id fields when model keys are not available', function (): void {
    $resolver = app(CouponTracePayloadResolver::class);
    $coupon = new CouponTracePayloadResolverTestCoupon(
        id: 456,
        code: 999,
        scope: 1,
        key: null,
    );

    expect($resolver->payload($coupon))->toMatchArray([
        'code' => '999',
        'id' => 456,
        'scope' => '1',
    ]);
});

it('normalizes missing or non scalar coupon fields', function (): void {
    $resolver = app(CouponTracePayloadResolver::class);

    expect($resolver->payload(new stdClass))->toBe([
        'code' => null,
        'id' => null,
        'scope' => '',
        'limit_qty' => null,
        'left_qty' => null,
    ]);
});
