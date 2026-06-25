<?php

use Illuminate\Contracts\Support\Arrayable;
use Lalalili\CommerceCore\Services\CheckoutOrderDataFactory;

it('builds checkout order data from cart-like items', function (): void {
    $factory = app(CheckoutOrderDataFactory::class);

    $order = $factory->fromCartItems(
        items: [
            (object) [
                'id' => 'SKU-1',
                'quantity' => 2,
                'name' => 'Package product',
                'price' => '900',
                'attributes' => new CheckoutOrderDataFactoryTestAttributes([
                    'type' => CheckoutOrderDataFactoryTestProductType::Book,
                    'company_id' => '10',
                    'custom' => 'kept',
                ]),
            ],
        ],
        attributes: ['payment_type' => 'credit'],
        fallbackUserId: '7',
    );

    expect($order->userId)->toBe(7)
        ->and($order->attributes)->toBe(['payment_type' => 'credit'])
        ->and($order->orderItems())->toBe([
            [
                'product_id' => 'SKU-1',
                'qty' => 2,
                'title' => 'Package product',
                'list_price' => 900,
                'sales_price' => 900,
                'product_type' => 1,
                'company_id' => 10,
            ],
        ])
        ->and($order->lines[0]->attributes)->toBe([
            'type' => CheckoutOrderDataFactoryTestProductType::Book,
            'company_id' => '10',
            'custom' => 'kept',
        ]);
});

it('allows host adapters to resolve item attributes explicitly', function (): void {
    $factory = app(CheckoutOrderDataFactory::class);
    $item = (object) [
        'id' => 123,
        'quantity' => 1,
        'name' => 'Course product',
        'price' => 1200,
        'hostAttributes' => [
            'type' => 5,
            'company_id' => 20,
        ],
    ];

    $order = $factory->fromCartItems(
        items: [$item],
        attributes: ['user_id' => 9],
        itemAttributesResolver: static fn (mixed $item): mixed => $item->hostAttributes ?? [],
    );

    expect($order->userId)->toBe(9)
        ->and($order->orderItems())->toBe([
            [
                'product_id' => 123,
                'qty' => 1,
                'title' => 'Course product',
                'list_price' => 1200,
                'sales_price' => 1200,
                'product_type' => 5,
                'company_id' => 20,
            ],
        ]);
});

it('requires a numeric checkout user id', function (): void {
    expect(fn () => app(CheckoutOrderDataFactory::class)->fromCartItems([], fallbackUserId: null))
        ->toThrow(InvalidArgumentException::class, 'Checkout user id is required.');
});

enum CheckoutOrderDataFactoryTestProductType: int
{
    case Book = 1;
}

/**
 * @implements Arrayable<string, mixed>
 */
class CheckoutOrderDataFactoryTestAttributes implements Arrayable
{
    /**
     * @param  array<string, mixed>  $items
     */
    public function __construct(private readonly array $items) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->items;
    }
}
