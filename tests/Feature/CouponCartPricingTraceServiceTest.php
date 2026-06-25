<?php

use Lalalili\CommerceCore\Services\CouponCartPricingTraceService;
use Lalalili\CommerceCore\Tests\Support\TraceTestCart;

it('appends coupon trace entries into cart context', function (): void {
    $cart = testCart([
        'pricing_trace' => [
            'shipping' => [
                ['stage' => 'shipping_quote', 'status' => 'selected'],
            ],
        ],
    ]);
    $service = app(CouponCartPricingTraceService::class);

    $service->appendCouponTrace($cart, [
        'stage' => 'coupon_apply',
        'source' => 'coupon',
        'kind' => 'member',
        'code' => 'WELCOME',
        'amount' => 50,
    ]);

    expect($cart->getContext()->get('pricing_trace'))->toMatchArray([
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
    ]);
});

it('ignores empty coupon trace entries without mutating cart context', function (): void {
    $cart = testCart(['pricing_trace' => ['coupon' => [['code' => 'KEEP']]]]);
    $originalContext = $cart->getContext();
    $service = app(CouponCartPricingTraceService::class);

    $service->appendCouponTrace($cart, []);

    expect($cart->getContext())->toBe($originalContext);
});

it('clears coupon trace entries by kind and code', function (): void {
    $cart = testCart([
        'pricing_trace' => [
            'coupon' => [
                ['stage' => 'coupon_apply', 'source' => 'coupon', 'kind' => 'member', 'code' => 'A'],
                ['stage' => 'coupon_apply', 'source' => 'coupon', 'kind' => 'promotion', 'code' => 'A'],
                ['stage' => 'coupon_apply', 'source' => 'coupon', 'kind' => 'promotion', 'code' => 'B'],
            ],
        ],
    ]);
    $service = app(CouponCartPricingTraceService::class);

    $service->clearCouponTrace($cart, kind: 'promotion', code: 'A');

    expect($cart->getContext()->get('pricing_trace')['coupon'])->toHaveCount(2)
        ->and(array_column($cart->getContext()->get('pricing_trace')['coupon'], 'code'))->toBe(['A', 'B'])
        ->and(array_column($cart->getContext()->get('pricing_trace')['coupon'], 'kind'))->toBe(['member', 'promotion']);
});

it('clears pricing trace when the stored trace is not an array', function (): void {
    $cart = testCart(['pricing_trace' => 'invalid']);
    $service = app(CouponCartPricingTraceService::class);

    $service->clearCouponTrace($cart);

    expect($cart->getContext()->get('pricing_trace'))->toBe([]);
});

it('rejects unsupported cart objects', function (): void {
    app(CouponCartPricingTraceService::class)->clearPricingTrace(new stdClass);
})->throws(InvalidArgumentException::class, 'Cart must expose getContext() and withContext() methods.');

/**
 * @param  array<string, mixed>  $context
 */
function testCart(array $context = []): TraceTestCart
{
    return new TraceTestCart($context);
}
