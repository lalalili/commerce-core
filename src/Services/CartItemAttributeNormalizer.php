<?php

namespace Lalalili\CommerceCore\Services;

use Illuminate\Support\Collection;

class CartItemAttributeNormalizer
{
    /**
     * @return array<string, mixed>
     */
    public function fromItem(mixed $item): array
    {
        return $this->normalize(data_get($item, 'attributes'));
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(mixed $attributes): array
    {
        if ($attributes instanceof Collection) {
            return $attributes->toArray();
        }

        if (is_array($attributes)) {
            return $attributes;
        }

        if ($attributes === null) {
            return [];
        }

        return (array) $attributes;
    }

    public function itemHasKey(mixed $item, string $key): bool
    {
        return array_key_exists($key, $this->fromItem($item));
    }
}
