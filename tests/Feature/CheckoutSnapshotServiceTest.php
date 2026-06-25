<?php

use Illuminate\Support\Collection;
use Lalalili\CommerceCore\Services\CheckoutSnapshotService;

it('captures cart items, conditions, totals, and a stable hash', function (): void {
    $service = app(CheckoutSnapshotService::class);
    $cart = new CheckoutSnapshotFakeCart(
        items: [
            new CheckoutSnapshotFakeItem(
                id: 'SKU-1',
                name: 'Package &amp; product',
                price: '100.50',
                quantity: 2,
                attributes: [
                    'prod_no' => 'P001',
                    'ignored' => 'hidden',
                ],
                conditions: [
                    new CheckoutSnapshotFakeCondition('Campaign', 'discount', '-10', [
                        'event_id' => 7,
                        'ignored' => 'hidden',
                    ]),
                ],
            ),
        ],
        conditions: new Collection([
            new CheckoutSnapshotFakeCondition('Coupon', 'coupon', '-20', [
                'sort' => 1,
                'ignored' => 'hidden',
            ]),
        ]),
        subtotal: '201',
        total: '171.5',
    );

    $snapshot = $service->captureCart($cart, ['prod_no']);
    $sameDataDifferentOrder = [
        'total' => 171.5,
        'subtotal' => 201,
        'conditions' => $snapshot['conditions'],
        'items' => $snapshot['items'],
    ];

    expect($snapshot)
        ->toHaveKeys(['items', 'conditions', 'subtotal', 'total', 'hash'])
        ->and($snapshot['items'][0]['name'])->toBe('Package & product')
        ->and($snapshot['items'][0]['price'])->toBe(100.5)
        ->and($snapshot['items'][0]['quantity'])->toBe(2)
        ->and($snapshot['items'][0]['price_with_conditions'])->toBe(90.5)
        ->and($snapshot['items'][0]['price_sum_with_conditions'])->toBe(181)
        ->and($snapshot['items'][0]['attributes'])->toBe(['prod_no' => 'P001'])
        ->and($snapshot['items'][0]['conditions'][0]['attributes'])->toBe(['event_id' => 7])
        ->and($snapshot['conditions'][0]['attributes'])->toBe(['sort' => 1])
        ->and($snapshot['subtotal'])->toBe(201)
        ->and($snapshot['total'])->toBe(171.5)
        ->and($snapshot['hash'])->toBe($service->hashPayload($sameDataDifferentOrder));
});

it('normalizes checkout line snapshots from a host supplied schema', function (): void {
    $service = app(CheckoutSnapshotService::class);

    $lines = $service->normalizeLineSnapshots([
        [
            'product_number' => 1001,
            'product_title' => 'Book',
            'quantity' => '2',
            'sales_price' => '450',
            'event_id' => [5],
            'is_preorder' => 1,
            'preorder_release_at' => now()->setDate(2026, 1, 2)->setTime(3, 4, 5),
        ],
    ], [
        'product_number' => ['type' => 'string', 'default' => ''],
        'product_title' => ['type' => 'string', 'default' => ''],
        'quantity' => ['type' => 'int', 'default' => 0],
        'sales_price' => ['type' => 'string', 'default' => ''],
        'event_id' => ['type' => 'array', 'default' => []],
        'is_preorder' => ['type' => 'bool', 'default' => false],
        'preorder_release_at' => ['type' => 'datetime', 'default' => null],
    ]);

    expect($lines)->toBe([
        [
            'product_number' => '1001',
            'product_title' => 'Book',
            'quantity' => 2,
            'sales_price' => '450',
            'event_id' => [5],
            'is_preorder' => true,
            'preorder_release_at' => '2026-01-02 03:04:05',
        ],
    ])
        ->and($service->detailTotal($lines))->toBe(900);
});

class CheckoutSnapshotFakeCart
{
    public function __construct(
        private readonly array $items,
        private readonly mixed $conditions,
        private readonly mixed $subtotal,
        private readonly mixed $total,
    ) {}

    public function getContent(): array
    {
        return $this->items;
    }

    public function getConditions(): mixed
    {
        return $this->conditions;
    }

    public function getSubTotal(bool $formatted = false): mixed
    {
        return $this->subtotal;
    }

    public function getTotal(bool $formatted = false): mixed
    {
        return $this->total;
    }
}

class CheckoutSnapshotFakeItem
{
    public function __construct(
        public readonly mixed $id,
        public readonly mixed $name,
        public readonly mixed $price,
        public readonly mixed $quantity,
        public readonly mixed $attributes,
        public readonly mixed $conditions,
    ) {}

    public function getPriceWithConditions(bool $formatted = false): mixed
    {
        return (float) $this->price - 10;
    }

    public function getPriceSumWithConditions(bool $formatted = false): mixed
    {
        return ((float) $this->price - 10) * (int) $this->quantity;
    }
}

class CheckoutSnapshotFakeCondition
{
    public function __construct(
        private readonly mixed $name,
        private readonly mixed $type,
        private readonly mixed $value,
        private readonly array $attributes,
    ) {}

    public function getName(): mixed
    {
        return $this->name;
    }

    public function getType(): mixed
    {
        return $this->type;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
