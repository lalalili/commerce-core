<?php

namespace Lalalili\CommerceCore\Support;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lalalili\CommerceCore\DTOs\OrderItemData;
use Lalalili\CommerceCore\Models\Product;

class OrderItemNormalizer
{
    public function __construct(private readonly ModelAttributeMapper $attributes) {}

    /**
     * @param  list<array{product_id:int|string, qty?:int, title?:string, list_price?:int, sales_price?:int, product_type?:int|null, company_id?:int|null}>  $items
     * @param  class-string<Model>  $productModel
     * @return list<OrderItemData>
     */
    public function normalize(array $items, string $productModel = Product::class): array
    {
        if ($items === []) {
            throw new InvalidArgumentException('Order items must not be empty.');
        }

        $normalizedItems = [];

        foreach ($items as $item) {
            /** @var Model|null $product */
            $product = $productModel::query()->find($item['product_id']);

            if (! $product instanceof Model) {
                throw new InvalidArgumentException("Product [{$item['product_id']}] does not exist.");
            }

            $quantity = max(1, (int) ($item['qty'] ?? 1));
            $listPrice = (int) ($item['list_price'] ?? $this->attributes->value($product, 'products', 'list_price', 0));
            $salesPrice = (int) ($item['sales_price'] ?? $this->attributes->value($product, 'products', 'sales_price', $listPrice));

            $normalizedItems[] = new OrderItemData(
                product: $product,
                quantity: $quantity,
                title: (string) ($item['title'] ?? $this->attributes->value($product, 'products', 'title')),
                listPrice: $listPrice,
                salesPrice: $salesPrice,
                productType: $item['product_type'] ?? $this->attributes->value($product, 'products', 'type'),
                companyId: $item['company_id'] ?? $this->attributes->value($product, 'products', 'company_id'),
            );
        }

        return $normalizedItems;
    }
}
