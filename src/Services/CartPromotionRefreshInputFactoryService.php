<?php

namespace Lalalili\CommerceCore\Services;

use UnexpectedValueException;

class CartPromotionRefreshInputFactoryService
{
    public const DEFAULT_CART_CONTEXT_CLASS = 'Lalalili\\Discount\\Contexts\\CartContext';

    public const DEFAULT_CART_LINE_CONTEXT_CLASS = 'Lalalili\\Discount\\Contexts\\CartLineContext';

    public const DEFAULT_REFRESH_INPUT_CLASS = 'Lalalili\\Discount\\DTOs\\CartPromotionRefreshInput';

    public function cartContext(string $cartContextClass = self::DEFAULT_CART_CONTEXT_CLASS): object
    {
        return new $cartContextClass(
            orderTotal: 0,
            allAmount: 0,
            bookAmount: 0,
            ebookAmount: 0,
            specificProductsAmount: 0,
            hasBook: false,
            hasEbook: false,
            hasSpecificProducts: false,
        );
    }

    /**
     * @param  list<object>  $lines
     * @param  array<int|string, object>  $promotionSetsByProductId
     */
    public function build(
        array $lines,
        array $promotionSetsByProductId,
        string $giftFulfillment,
        ?object $cartContext = null,
        string $refreshInputClass = self::DEFAULT_REFRESH_INPUT_CLASS,
        string $cartContextClass = self::DEFAULT_CART_CONTEXT_CLASS,
    ): object {
        return new $refreshInputClass(
            cartContext: $cartContext ?? $this->cartContext($cartContextClass),
            lines: $lines,
            promotionSetsByProductId: $promotionSetsByProductId,
            giftFulfillment: $giftFulfillment,
        );
    }

    /**
     * @param  iterable<array{
     *     id: int|string|null,
     *     product_id: int|string|null,
     *     quantity: int,
     *     unit_price: float,
     *     associated_model: string,
     *     attributes: array<string, mixed>
     * }>  $payloads
     * @return list<object>
     */
    public function linesFromPayloads(
        iterable $payloads,
        string $cartLineContextClass = self::DEFAULT_CART_LINE_CONTEXT_CLASS,
    ): array {
        $lines = [];

        foreach ($payloads as $payload) {
            $lines[] = $this->lineFromPayload($payload, $cartLineContextClass);
        }

        return $lines;
    }

    /**
     * @param  array{
     *     id: int|string|null,
     *     product_id: int|string|null,
     *     quantity: int,
     *     unit_price: float,
     *     associated_model: string,
     *     attributes: array<string, mixed>
     * }  $payload
     */
    public function lineFromPayload(
        array $payload,
        string $cartLineContextClass = self::DEFAULT_CART_LINE_CONTEXT_CLASS,
    ): object {
        if ($payload['id'] === null || $payload['product_id'] === null) {
            throw new UnexpectedValueException('Cart promotion line payload must include product identifiers.');
        }

        return new $cartLineContextClass(
            id: $payload['id'],
            productId: $payload['product_id'],
            quantity: $payload['quantity'],
            unitPrice: $payload['unit_price'],
            associatedModel: $payload['associated_model'],
            attributes: $payload['attributes'],
        );
    }
}
