<?php

namespace Lalalili\CommerceCore\Contracts;

use Lalalili\CommerceCore\DTOs\CheckoutOrderData;

interface CheckoutOrderBuilder
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function build(mixed $checkoutCart, array $attributes = []): CheckoutOrderData;
}
