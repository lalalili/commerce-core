<?php

namespace Lalalili\CommerceCore\Services;

use InvalidArgumentException;

class CouponCartPricingTraceService
{
    public const DEFAULT_CONTEXT_KEY = 'pricing_trace';

    public function __construct(private readonly CouponPricingTraceService $traces) {}

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>  $entries
     */
    public function appendCouponTrace(
        mixed $cart,
        array $entries,
        int $maxEntries = CouponPricingTraceService::DEFAULT_MAX_COUPON_TRACE_ENTRIES,
        string $contextKey = self::DEFAULT_CONTEXT_KEY,
    ): void {
        $normalizedEntries = $this->traces->normalizeEntries($entries);
        if ($normalizedEntries === []) {
            return;
        }

        $cart = $this->cart($cart);
        $context = $this->cartContext($cart);
        $pricingTrace = $this->contextGet($context, $contextKey, []);
        $pricingTrace = is_array($pricingTrace) ? $pricingTrace : [];

        $this->cartWithContext($cart, $this->contextWith(
            $context,
            $contextKey,
            $this->traces->appendCouponTrace($pricingTrace, $normalizedEntries, $maxEntries)
        ));
    }

    public function clearPricingTrace(mixed $cart, string $contextKey = self::DEFAULT_CONTEXT_KEY): void
    {
        $cart = $this->cart($cart);
        $context = $this->cartContext($cart);

        $this->cartWithContext($cart, $this->contextWith($context, $contextKey, []));
    }

    public function clearCouponTrace(
        mixed $cart,
        ?string $kind = null,
        ?string $code = null,
        string $contextKey = self::DEFAULT_CONTEXT_KEY,
    ): void {
        $cart = $this->cart($cart);
        $context = $this->cartContext($cart);
        $pricingTrace = $this->contextGet($context, $contextKey, []);

        if (! is_array($pricingTrace)) {
            $this->clearPricingTrace($cart, $contextKey);

            return;
        }

        $this->cartWithContext($cart, $this->contextWith(
            $context,
            $contextKey,
            $this->traces->clearCouponTrace($pricingTrace, $kind, $code)
        ));
    }

    private function cart(mixed $cart): object
    {
        if (! is_object($cart) || ! method_exists($cart, 'getContext') || ! method_exists($cart, 'withContext')) {
            throw new InvalidArgumentException('Cart must expose getContext() and withContext() methods.');
        }

        return $cart;
    }

    private function cartContext(object $cart): object
    {
        $context = $this->invoke($cart, 'getContext');
        if (! is_object($context) || ! method_exists($context, 'get') || ! method_exists($context, 'with')) {
            throw new InvalidArgumentException('Cart context must expose get() and with() methods.');
        }

        return $context;
    }

    private function cartWithContext(object $cart, object $context): void
    {
        $this->invoke($cart, 'withContext', [$context]);
    }

    private function contextGet(object $context, string $key, mixed $default = null): mixed
    {
        return $this->invoke($context, 'get', [$key, $default]);
    }

    private function contextWith(object $context, string $key, mixed $value): object
    {
        $nextContext = $this->invoke($context, 'with', [$key, $value]);
        if (! is_object($nextContext)) {
            throw new InvalidArgumentException('Cart context with() method must return an object.');
        }

        return $nextContext;
    }

    /**
     * @param  list<mixed>  $arguments
     */
    private function invoke(object $object, string $method, array $arguments = []): mixed
    {
        $callback = [$object, $method];
        if (! is_callable($callback)) {
            throw new InvalidArgumentException("Method [{$method}] is not callable.");
        }

        return $callback(...$arguments);
    }
}
