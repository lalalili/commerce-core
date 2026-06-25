<?php

namespace Lalalili\CommerceCore\Services;

use InvalidArgumentException;
use Lalalili\CommerceCore\DTOs\CouponCheckoutResult;
use Throwable;

class CouponCheckoutAdapterService
{
    public function assertCheckoutCart(
        mixed $checkoutCart,
        ?string $expectedCartClass,
        ?string $invalidCartMessage = null,
    ): mixed {
        if ($expectedCartClass !== null && ! $checkoutCart instanceof $expectedCartClass) {
            throw new InvalidArgumentException($invalidCartMessage ?? "Checkout cart must be a {$expectedCartClass} instance.");
        }

        return $checkoutCart;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function resultFromResponse(array $response): CouponCheckoutResult
    {
        $data = $response['data'] ?? [];

        return new CouponCheckoutResult(
            successful: (bool) ($response['success'] ?? false),
            message: (string) ($response['message'] ?? ''),
            data: is_array($data) ? $data : [],
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function orderTotal(
        mixed $checkoutCart,
        array $context = [],
        string $contextKey = 'order_total',
        ?callable $fallback = null,
    ): float {
        $contextTotal = $context[$contextKey] ?? null;
        if (is_numeric($contextTotal)) {
            return (float) $contextTotal;
        }

        $fallbackTotal = $fallback !== null
            ? $this->safeValue($fallback)
            : $this->cartTotal($checkoutCart);

        return is_numeric($fallbackTotal) ? (float) $fallbackTotal : 0.0;
    }

    private function cartTotal(mixed $checkoutCart): mixed
    {
        if (! is_object($checkoutCart) || ! method_exists($checkoutCart, 'getTotal')) {
            return null;
        }

        return $this->safeValue(static fn (): mixed => $checkoutCart->getTotal(false));
    }

    private function safeValue(callable $resolver): mixed
    {
        try {
            return $resolver();
        } catch (Throwable) {
            return null;
        }
    }
}
