<?php

use Lalalili\CommerceCore\Models\Product;
use Lalalili\CommerceCore\Support\ModelAttributeMapper;
use Lalalili\CommerceCore\Support\OrderItemNormalizer;

function makeOrderItemNormalizer(): OrderItemNormalizer
{
    return new OrderItemNormalizer(new ModelAttributeMapper);
}

it('falls back to product attributes when item fields are omitted', function (): void {
    $product = Product::query()->create([
        'title' => 'Catalog title',
        'type' => 3,
        'list_price' => 1200,
        'sales_price' => 900,
        'tax' => 1,
        'company_id' => 7,
    ]);

    $items = makeOrderItemNormalizer()->normalize([
        ['product_id' => $product->id, 'qty' => 2],
    ]);

    expect($items)->toHaveCount(1)
        ->and($items[0]->title)->toBe('Catalog title')
        ->and($items[0]->listPrice)->toBe(1200)
        ->and($items[0]->salesPrice)->toBe(900)
        ->and($items[0]->quantity)->toBe(2)
        ->and($items[0]->productType)->toBe(3)
        ->and($items[0]->companyId)->toBe(7)
        ->and($items[0]->totalListPrice())->toBe(2400)
        ->and($items[0]->totalSalesPrice())->toBe(1800);
});

it('prefers explicit item attributes over product defaults', function (): void {
    $product = Product::query()->create([
        'title' => 'Catalog title',
        'type' => 1,
        'list_price' => 1200,
        'sales_price' => 900,
        'tax' => 1,
    ]);

    $items = makeOrderItemNormalizer()->normalize([
        [
            'product_id' => $product->id,
            'qty' => 1,
            'title' => 'Checkout title',
            'list_price' => 1500,
            'sales_price' => 1000,
            'product_type' => 9,
            'company_id' => 20,
        ],
    ]);

    expect($items[0]->title)->toBe('Checkout title')
        ->and($items[0]->listPrice)->toBe(1500)
        ->and($items[0]->salesPrice)->toBe(1000)
        ->and($items[0]->productType)->toBe(9)
        ->and($items[0]->companyId)->toBe(20);
});

it('clamps quantity to a minimum of one', function (): void {
    $product = Product::query()->create([
        'title' => 'Min qty',
        'type' => 1,
        'list_price' => 100,
        'sales_price' => 100,
        'tax' => 1,
    ]);

    $zero = makeOrderItemNormalizer()->normalize([['product_id' => $product->id, 'qty' => 0]]);
    $negative = makeOrderItemNormalizer()->normalize([['product_id' => $product->id, 'qty' => -5]]);

    expect($zero[0]->quantity)->toBe(1)
        ->and($negative[0]->quantity)->toBe(1);
});

it('rejects empty item lists', function (): void {
    expect(fn () => makeOrderItemNormalizer()->normalize([]))
        ->toThrow(InvalidArgumentException::class, 'Order items must not be empty.');
});

it('rejects items whose product does not exist', function (): void {
    expect(fn () => makeOrderItemNormalizer()->normalize([['product_id' => 'missing-product']]))
        ->toThrow(InvalidArgumentException::class, 'Product [missing-product] does not exist.');
});
