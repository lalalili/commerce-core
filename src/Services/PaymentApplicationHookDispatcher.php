<?php

namespace Lalalili\CommerceCore\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lalalili\CommerceCore\Contracts\PaymentApplicationHook;
use Lalalili\CommerceCore\DTOs\PaymentApplicationData;

class PaymentApplicationHookDispatcher
{
    public function __construct(private readonly Container $container) {}

    public function afterApplied(PaymentApplicationData $payment, ?Model $order): void
    {
        foreach ($this->hooks() as $hook) {
            $hook->afterApplied($payment, $order);
        }
    }

    /**
     * @return list<PaymentApplicationHook>
     */
    private function hooks(): array
    {
        $configuredHooks = config('commerce.payment.hooks', []);
        if (! is_array($configuredHooks)) {
            return [];
        }

        return array_values(array_map(
            fn (mixed $hook): PaymentApplicationHook => $this->resolveHook($hook),
            $configuredHooks,
        ));
    }

    private function resolveHook(mixed $hook): PaymentApplicationHook
    {
        if (is_string($hook) && class_exists($hook)) {
            $hook = $this->container->make($hook);
        }

        if (! $hook instanceof PaymentApplicationHook) {
            throw new InvalidArgumentException('Commerce payment hooks must implement '.PaymentApplicationHook::class.'.');
        }

        return $hook;
    }
}
