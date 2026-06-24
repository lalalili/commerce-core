<?php

namespace Lalalili\CommerceCore\Services;

use Lalalili\CommerceCore\Contracts\CheckoutCartAccessor;
use Lalalili\CommerceCore\Contracts\CheckoutOrderBuilder;
use Lalalili\CommerceCore\Contracts\CouponCheckoutAdapter;
use Lalalili\CommerceCore\DTOs\CheckoutOrderData;
use Lalalili\CommerceCore\DTOs\CouponCheckoutResult;

class CheckoutService
{
    public function __construct(
        private readonly CheckoutCartAccessor $carts,
        private readonly CouponCheckoutAdapter $coupons,
        private readonly CheckoutOrderBuilder $orders,
    ) {}

    public function cart(): mixed
    {
        return $this->carts->cart();
    }

    public function checkoutCart(): mixed
    {
        return $this->carts->checkoutCart();
    }

    public function applyCoupon(string $kind, string $code): CouponCheckoutResult
    {
        return $this->coupons->apply($kind, $code, $this->checkoutCart());
    }

    public function clearCoupon(string $kind): void
    {
        $this->coupons->clear($kind, $this->checkoutCart());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function buildOrder(array $attributes = []): CheckoutOrderData
    {
        return $this->orders->build($this->checkoutCart(), $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function complete(array $attributes = []): CheckoutOrderData
    {
        $order = $this->buildOrder($attributes);

        $this->carts->completeCheckout();

        return $order;
    }
}
