<?php

use Lalalili\CommerceCore\Contracts\CheckoutCartAccessor;
use Lalalili\CommerceCore\Contracts\CheckoutOrderBuilder;
use Lalalili\CommerceCore\Contracts\CouponCheckoutAdapter;
use Lalalili\CommerceCore\DTOs\CheckoutLineData;
use Lalalili\CommerceCore\DTOs\CheckoutOrderData;
use Lalalili\CommerceCore\DTOs\CouponCheckoutResult;
use Lalalili\CommerceCore\Services\CheckoutService;

beforeEach(function (): void {
    CheckoutServiceFakeCartAccessor::reset();
    CheckoutServiceFakeCouponAdapter::reset();
    CheckoutServiceFakeOrderBuilder::reset();

    app()->bind(CheckoutCartAccessor::class, CheckoutServiceFakeCartAccessor::class);
    app()->bind(CouponCheckoutAdapter::class, CheckoutServiceFakeCouponAdapter::class);
    app()->bind(CheckoutOrderBuilder::class, CheckoutServiceFakeOrderBuilder::class);
});

it('applies coupons against the checkout cart', function (): void {
    $result = app(CheckoutService::class)->applyCoupon('member', 'MEMBER-100', ['order_total' => 900]);

    expect($result->toArray())->toBe([
        'success' => true,
        'message' => 'applied',
        'data' => [
            'kind' => 'member',
            'code' => 'MEMBER-100',
        ],
    ])
        ->and(CheckoutServiceFakeCouponAdapter::$applied)->toBe([
            [
                'kind' => 'member',
                'code' => 'MEMBER-100',
                'cart' => 'checkout-cart',
                'context' => ['order_total' => 900],
            ],
        ]);
});

it('clears coupons against the checkout cart', function (): void {
    app(CheckoutService::class)->clearCoupon('promotion');

    expect(CheckoutServiceFakeCouponAdapter::$cleared)->toBe([
        [
            'kind' => 'promotion',
            'cart' => 'checkout-cart',
        ],
    ]);
});

it('builds checkout order data through the configured order builder', function (): void {
    $order = app(CheckoutService::class)->buildOrder(['payment_type' => 'credit']);

    expect($order->userId)->toBe(7)
        ->and($order->attributes)->toBe(['payment_type' => 'credit'])
        ->and($order->orderItems())->toBe([
            [
                'product_id' => 'SKU-1',
                'qty' => 2,
                'title' => 'Package product',
                'list_price' => 1000,
                'sales_price' => 900,
                'product_type' => 1,
                'company_id' => 10,
            ],
        ])
        ->and(CheckoutServiceFakeOrderBuilder::$built)->toBe([
            [
                'cart' => 'checkout-cart',
                'attributes' => ['payment_type' => 'credit'],
            ],
        ]);
});

it('finalizes cart sessions after completing checkout', function (): void {
    $order = app(CheckoutService::class)->complete(['source' => 'web']);

    expect($order)->toBeInstanceOf(CheckoutOrderData::class)
        ->and(CheckoutServiceFakeCartAccessor::$completed)->toBe(1)
        ->and(CheckoutServiceFakeCartAccessor::$cleared)->toBe([]);
});

it('does not clear carts when checkout order building fails', function (): void {
    CheckoutServiceFakeOrderBuilder::$shouldFail = true;

    expect(fn (): CheckoutOrderData => app(CheckoutService::class)->complete())
        ->toThrow(RuntimeException::class, 'Unable to build checkout order')
        ->and(CheckoutServiceFakeCartAccessor::$cleared)->toBe([]);
});

class CheckoutServiceFakeCartAccessor implements CheckoutCartAccessor
{
    /**
     * @var list<string>
     */
    public static array $cleared = [];

    public static int $completed = 0;

    public static function reset(): void
    {
        self::$cleared = [];
        self::$completed = 0;
    }

    public function cart(): mixed
    {
        return 'cart';
    }

    public function checkoutCart(): mixed
    {
        return 'checkout-cart';
    }

    public function clearCart(): void
    {
        self::$cleared[] = 'cart';
    }

    public function clearCheckoutCart(): void
    {
        self::$cleared[] = 'checkout';
    }

    public function completeCheckout(): void
    {
        self::$completed++;
    }
}

class CheckoutServiceFakeCouponAdapter implements CouponCheckoutAdapter
{
    /**
     * @var list<array{kind:string, code:string, cart:mixed, context:array<string, mixed>}>
     */
    public static array $applied = [];

    /**
     * @var list<array{kind:string, cart:mixed}>
     */
    public static array $cleared = [];

    public static function reset(): void
    {
        self::$applied = [];
        self::$cleared = [];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function apply(string $kind, string $code, mixed $checkoutCart, array $context = []): CouponCheckoutResult
    {
        self::$applied[] = [
            'kind' => $kind,
            'code' => $code,
            'cart' => $checkoutCart,
            'context' => $context,
        ];

        return new CouponCheckoutResult(true, 'applied', [
            'kind' => $kind,
            'code' => $code,
        ]);
    }

    public function clear(string $kind, mixed $checkoutCart): void
    {
        self::$cleared[] = [
            'kind' => $kind,
            'cart' => $checkoutCart,
        ];
    }
}

class CheckoutServiceFakeOrderBuilder implements CheckoutOrderBuilder
{
    /**
     * @var list<array{cart:mixed, attributes:array<string, mixed>}>
     */
    public static array $built = [];

    public static bool $shouldFail = false;

    public static function reset(): void
    {
        self::$built = [];
        self::$shouldFail = false;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function build(mixed $checkoutCart, array $attributes = []): CheckoutOrderData
    {
        if (self::$shouldFail) {
            throw new RuntimeException('Unable to build checkout order');
        }

        self::$built[] = [
            'cart' => $checkoutCart,
            'attributes' => $attributes,
        ];

        return new CheckoutOrderData(
            userId: 7,
            lines: [
                new CheckoutLineData(
                    productId: 'SKU-1',
                    quantity: 2,
                    title: 'Package product',
                    listPrice: 1000,
                    salesPrice: 900,
                    productType: 1,
                    companyId: 10,
                ),
            ],
            attributes: $attributes,
        );
    }
}
