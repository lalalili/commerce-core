<?php

use Lalalili\CommerceCore\Services\CouponDataFactory;

enum CouponDataFactoryTestKind: string
{
    case Member = 'member';
    case Promotion = 'promotion';
}

class CouponDataFactoryTestCouponData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $code,
        public CouponDataFactoryTestKind $kind,
        public int $scope,
        public int|float|null $triggerAmount,
        public float $amount,
        public ?string $amountMode,
        public bool $status,
        public ?int $limitQty,
        public ?int $leftQty,
        public ?int $userId,
        public array $attributes,
    ) {}
}

class CouponDataFactoryTestCoupon
{
    public string $code = 'PROMO10';

    public int $scope = 2;

    public string $trigger_amount = '500';

    public string $amount = '75.5';

    public string $amount_mode = 'fixed';

    public int $status = 1;

    public ?string $limit_qty = '20';

    public ?string $left_qty = '8';

    public ?string $user_id = null;
}

it('builds coupon data from normalized payloads', function (): void {
    $couponData = app(CouponDataFactory::class)->fromPayload([
        'code' => 'MEMBER5',
        'scope' => 1,
        'trigger_amount' => 100,
        'amount' => 5.0,
        'amount_mode' => 'percent',
        'status' => true,
        'limit_qty' => null,
        'left_qty' => null,
        'user_id' => 42,
        'attributes' => [
            'title' => 'Member coupon',
        ],
    ], CouponDataFactoryTestKind::Member, CouponDataFactoryTestCouponData::class);

    expect($couponData)->toBeInstanceOf(CouponDataFactoryTestCouponData::class)
        ->code->toBe('MEMBER5')
        ->kind->toBe(CouponDataFactoryTestKind::Member)
        ->scope->toBe(1)
        ->triggerAmount->toBe(100)
        ->amount->toBe(5.0)
        ->amountMode->toBe('percent')
        ->status->toBeTrue()
        ->limitQty->toBeNull()
        ->leftQty->toBeNull()
        ->userId->toBe(42)
        ->attributes->toBe(['title' => 'Member coupon']);
});

it('builds coupon data directly from host coupon-like models', function (): void {
    $couponData = app(CouponDataFactory::class)->fromCoupon(
        coupon: new CouponDataFactoryTestCoupon,
        kind: CouponDataFactoryTestKind::Promotion,
        couponDataClass: CouponDataFactoryTestCouponData::class,
        attributes: [
            'id' => 99,
            'scope_products' => [10, 20],
        ],
    );

    expect($couponData)->toBeInstanceOf(CouponDataFactoryTestCouponData::class)
        ->code->toBe('PROMO10')
        ->kind->toBe(CouponDataFactoryTestKind::Promotion)
        ->scope->toBe(2)
        ->triggerAmount->toBe(500)
        ->amount->toBe(75.5)
        ->amountMode->toBe('fixed')
        ->status->toBeTrue()
        ->limitQty->toBe(20)
        ->leftQty->toBe(8)
        ->userId->toBeNull()
        ->attributes->toBe([
            'id' => 99,
            'scope_products' => [10, 20],
        ]);
});
