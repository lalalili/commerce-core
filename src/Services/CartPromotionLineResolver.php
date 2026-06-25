<?php

namespace Lalalili\CommerceCore\Services;

use Illuminate\Support\Collection;

class CartPromotionLineResolver
{
    public function __construct(private readonly CartItemAttributeNormalizer $attributes) {}

    /**
     * @param  iterable<mixed>  $content
     * @param  callable(mixed): bool  $productExists
     * @param  callable(mixed): mixed|null  $attributesResolver
     * @return list<array{
     *     id: int|string|null,
     *     product_id: int|string|null,
     *     quantity: int,
     *     unit_price: float,
     *     associated_model: string,
     *     attributes: array<string, mixed>
     * }>
     */
    public function payloads(
        iterable $content,
        callable $productExists,
        ?callable $attributesResolver = null,
        string $productAssociatedModel = 'Product',
    ): array {
        $payloads = [];

        foreach ($this->sortedItems($content) as $item) {
            $associatedModel = $this->associatedModel($item, $productAssociatedModel);
            if ($associatedModel !== $productAssociatedModel) {
                continue;
            }

            $productId = $this->itemId($item);
            if (! $productExists($productId)) {
                continue;
            }

            $payloads[] = [
                'id' => $productId,
                'product_id' => $productId,
                'quantity' => (int) data_get($item, 'quantity', 0),
                'unit_price' => (float) data_get($item, 'price', 0),
                'associated_model' => $associatedModel,
                'attributes' => $this->attributesFor($item, $attributesResolver),
            ];
        }

        return $payloads;
    }

    /**
     * @param  iterable<mixed>  $content
     * @return array<int, mixed>
     */
    private function sortedItems(iterable $content): array
    {
        if ($content instanceof Collection) {
            return $content->sortBy('id')->values()->all();
        }

        $items = is_array($content) ? array_values($content) : iterator_to_array($content, false);

        usort($items, static fn (mixed $left, mixed $right): int => (string) data_get($left, 'id') <=> (string) data_get($right, 'id'));

        return $items;
    }

    private function associatedModel(mixed $item, string $default): string
    {
        $associatedModel = data_get($item, 'associatedModel', $default);

        return is_string($associatedModel) && $associatedModel !== '' ? $associatedModel : $default;
    }

    private function itemId(mixed $item): int|string|null
    {
        $id = data_get($item, 'id');

        return is_int($id) || is_string($id) ? $id : null;
    }

    /**
     * @param  callable(mixed): mixed|null  $attributesResolver
     * @return array<string, mixed>
     */
    private function attributesFor(mixed $item, ?callable $attributesResolver): array
    {
        if ($attributesResolver === null) {
            return $this->attributes->fromItem($item);
        }

        return $this->attributes->normalize($attributesResolver($item));
    }
}
