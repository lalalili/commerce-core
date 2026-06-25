<?php

use Lalalili\CommerceCore\Services\CheckoutOrderBuilderService;

it('builds checkout order data from a host cart content resolver', function (): void {
    $service = app(CheckoutOrderBuilderService::class);
    $cart = new CheckoutOrderBuilderFakeCart([
        new CheckoutOrderBuilderFakeItem(
            id: 'SKU-1',
            name: 'Course',
            price: '1200',
            quantity: 2,
            attributes: [
                'type' => CheckoutOrderBuilderFakeProductType::Course,
                'company_id' => '7',
            ],
        ),
    ]);

    $order = $service->build(
        checkoutCart: $cart,
        expectedCartClass: CheckoutOrderBuilderFakeCart::class,
        itemsResolver: static fn (CheckoutOrderBuilderFakeCart $cart): array => $cart->items,
        attributes: ['source' => 'checkout'],
        fallbackUserId: '42',
    );

    expect($order->userId)->toBe(42)
        ->and($order->attributes)->toBe(['source' => 'checkout'])
        ->and($order->orderItems())->toBe([
            [
                'product_id' => 'SKU-1',
                'qty' => 2,
                'title' => 'Course',
                'list_price' => 1200,
                'sales_price' => 1200,
                'product_type' => 3,
                'company_id' => 7,
            ],
        ]);
});

it('allows hosts to resolve item attributes explicitly', function (): void {
    $service = app(CheckoutOrderBuilderService::class);
    $cart = new CheckoutOrderBuilderFakeCart([
        new CheckoutOrderBuilderFakeItem(
            id: 'SKU-1',
            name: 'Course',
            price: '1200',
            quantity: 1,
            attributes: [],
        ),
    ]);

    $order = $service->build(
        checkoutCart: $cart,
        expectedCartClass: CheckoutOrderBuilderFakeCart::class,
        itemsResolver: static fn (CheckoutOrderBuilderFakeCart $cart): array => $cart->items,
        fallbackUserId: 42,
        itemAttributesResolver: static fn (): array => [
            'type' => 3,
            'company_id' => 7,
        ],
    );

    expect($order->orderItems()[0])
        ->toMatchArray([
            'product_type' => 3,
            'company_id' => 7,
        ]);
});

it('rejects carts that do not match the expected host class', function (): void {
    $service = app(CheckoutOrderBuilderService::class);

    expect(fn () => $service->build(
        checkoutCart: new stdClass,
        expectedCartClass: CheckoutOrderBuilderFakeCart::class,
        itemsResolver: static fn (): array => [],
        fallbackUserId: 42,
        invalidCartMessage: 'Checkout cart must be a fake cart.',
    ))->toThrow(InvalidArgumentException::class, 'Checkout cart must be a fake cart.');
});

class CheckoutOrderBuilderFakeCart
{
    /**
     * @param  list<CheckoutOrderBuilderFakeItem>  $items
     */
    public function __construct(public array $items) {}
}

class CheckoutOrderBuilderFakeItem
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $id,
        public string $name,
        public int|string $price,
        public int $quantity,
        public array $attributes,
    ) {}
}

enum CheckoutOrderBuilderFakeProductType: int
{
    case Course = 3;
}
