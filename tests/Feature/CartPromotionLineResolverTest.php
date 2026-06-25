<?php

use Illuminate\Support\Collection;
use Lalalili\CommerceCore\Services\CartPromotionLineResolver;

class CartPromotionLineResolverTestItem
{
    /**
     * @param  array<string, mixed>|Collection<string, mixed>|null  $attributes
     */
    public function __construct(
        public int|string|null $id,
        public int $quantity = 1,
        public int|float|string $price = 100,
        public ?string $associatedModel = 'Product',
        public array|Collection|null $attributes = null,
    ) {}
}

it('builds sorted promotion line payloads for product cart items', function (): void {
    $resolver = app(CartPromotionLineResolver::class);
    $content = collect([
        new CartPromotionLineResolverTestItem(id: 20, quantity: 2, price: '150.5', attributes: collect(['gift' => true])),
        new CartPromotionLineResolverTestItem(id: 10, quantity: 1, price: 99),
        new CartPromotionLineResolverTestItem(id: 30, associatedModel: 'Shipping'),
    ]);

    $payloads = $resolver->payloads(
        content: $content,
        productExists: static fn (mixed $productId): bool => in_array($productId, [10, 20], true),
    );

    expect($payloads)->toBe([
        [
            'id' => 10,
            'product_id' => 10,
            'quantity' => 1,
            'unit_price' => 99.0,
            'associated_model' => 'Product',
            'attributes' => [],
        ],
        [
            'id' => 20,
            'product_id' => 20,
            'quantity' => 2,
            'unit_price' => 150.5,
            'associated_model' => 'Product',
            'attributes' => ['gift' => true],
        ],
    ]);
});

it('lets hosts resolve attributes explicitly', function (): void {
    $resolver = app(CartPromotionLineResolver::class);
    $item = new CartPromotionLineResolverTestItem(id: 'A100', attributes: ['ignored' => true]);

    $payloads = $resolver->payloads(
        content: [$item],
        productExists: static fn (mixed $productId): bool => $productId === 'A100',
        attributesResolver: static fn (mixed $item): array => [
            'item_id' => $item->id,
        ],
    );

    expect($payloads[0]['attributes'])->toBe(['item_id' => 'A100']);
});

it('filters lines without matching host products', function (): void {
    $resolver = app(CartPromotionLineResolver::class);

    $payloads = $resolver->payloads(
        content: [
            new CartPromotionLineResolverTestItem(id: null),
            new CartPromotionLineResolverTestItem(id: 99),
        ],
        productExists: static fn (mixed $productId): bool => $productId === 1,
    );

    expect($payloads)->toBe([]);
});
