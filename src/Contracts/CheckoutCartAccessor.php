<?php

namespace Lalalili\CommerceCore\Contracts;

interface CheckoutCartAccessor
{
    public function cart(): mixed;

    public function checkoutCart(): mixed;

    public function clearCart(): void;

    public function clearCheckoutCart(): void;

    public function completeCheckout(): void;
}
