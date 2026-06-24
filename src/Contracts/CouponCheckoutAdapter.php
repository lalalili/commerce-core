<?php

namespace Lalalili\CommerceCore\Contracts;

use Lalalili\CommerceCore\DTOs\CouponCheckoutResult;

interface CouponCheckoutAdapter
{
    public function apply(string $kind, string $code, mixed $checkoutCart): CouponCheckoutResult;

    public function clear(string $kind, mixed $checkoutCart): void;
}
