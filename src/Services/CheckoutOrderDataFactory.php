<?php

namespace Lalalili\CommerceCore\Services;

use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use Lalalili\CommerceCore\DTOs\CheckoutLineData;
use Lalalili\CommerceCore\DTOs\CheckoutOrderData;

class CheckoutOrderDataFactory
{
    /**
     * @param  iterable<int, mixed>  $items
     * @param  array<string, mixed>  $attributes
     * @param  callable(mixed): mixed|null  $itemAttributesResolver
     */
    public function fromCartItems(
        iterable $items,
        array $attributes = [],
        mixed $fallbackUserId = null,
        ?callable $itemAttributesResolver = null,
    ): CheckoutOrderData {
        $lines = [];

        foreach ($items as $item) {
            $itemAttributes = $this->itemAttributesToArray(
                $itemAttributesResolver !== null
                    ? $itemAttributesResolver($item)
                    : data_get($item, 'attributes', [])
            );

            $lines[] = new CheckoutLineData(
                productId: data_get($item, 'id', ''),
                quantity: (int) data_get($item, 'quantity', 1),
                title: is_string(data_get($item, 'name')) ? data_get($item, 'name') : null,
                listPrice: $this->nullableInt(data_get($item, 'price')),
                salesPrice: $this->nullableInt(data_get($item, 'price')),
                productType: $this->nullableInt($this->enumValue($itemAttributes['type'] ?? null)),
                companyId: $this->nullableInt($itemAttributes['company_id'] ?? null),
                attributes: $itemAttributes,
            );
        }

        return new CheckoutOrderData(
            userId: $this->resolveUserId($attributes, $fallbackUserId),
            lines: $lines,
            attributes: $attributes,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function resolveUserId(array $attributes, mixed $fallbackUserId = null): int
    {
        $userId = $attributes['user_id'] ?? $fallbackUserId;

        if (! is_numeric($userId)) {
            throw new InvalidArgumentException('Checkout user id is required.');
        }

        return (int) $userId;
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function itemAttributesToArray(mixed $attributes): array
    {
        if ($attributes instanceof Arrayable) {
            return $attributes->toArray();
        }

        return is_array($attributes) ? $attributes : [];
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
