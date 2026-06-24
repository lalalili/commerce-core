<?php

namespace Lalalili\CommerceCore\Contracts;

use Lalalili\CommerceCore\DTOs\CouponCheckoutResult;

interface CouponCheckoutAdapter
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function apply(string $kind, string $code, mixed $checkoutCart, array $context = []): CouponCheckoutResult;

    public function clear(string $kind, mixed $checkoutCart): void;
}
