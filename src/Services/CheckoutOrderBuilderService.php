<?php

namespace Lalalili\CommerceCore\Services;

use InvalidArgumentException;
use Lalalili\CommerceCore\DTOs\CheckoutOrderData;

class CheckoutOrderBuilderService
{
    public function __construct(private readonly CheckoutOrderDataFactory $orders) {}

    /**
     * @param  class-string|null  $expectedCartClass
     * @param  array<string, mixed>  $attributes
     * @param  callable(mixed): iterable<mixed>  $itemsResolver
     * @param  callable(mixed): mixed|null  $itemAttributesResolver
     */
    public function build(
        mixed $checkoutCart,
        ?string $expectedCartClass,
        callable $itemsResolver,
        array $attributes = [],
        mixed $fallbackUserId = null,
        ?callable $itemAttributesResolver = null,
        ?string $invalidCartMessage = null,
    ): CheckoutOrderData {
        if ($expectedCartClass !== null && ! $checkoutCart instanceof $expectedCartClass) {
            throw new InvalidArgumentException($invalidCartMessage ?? "Checkout cart must be a {$expectedCartClass} instance.");
        }

        return $this->orders->fromCartItems(
            items: array_values($this->iterableToArray($itemsResolver($checkoutCart))),
            attributes: $attributes,
            fallbackUserId: $fallbackUserId,
            itemAttributesResolver: $itemAttributesResolver,
        );
    }

    /**
     * @param  iterable<mixed>  $items
     * @return array<mixed>
     */
    private function iterableToArray(iterable $items): array
    {
        if (is_array($items)) {
            return $items;
        }

        return iterator_to_array($items);
    }
}
