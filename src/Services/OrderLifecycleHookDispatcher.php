<?php

namespace Lalalili\CommerceCore\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lalalili\CommerceCore\Contracts\OrderCancellationLifecycleHook;
use Lalalili\CommerceCore\Contracts\OrderFulfillmentLifecycleHook;
use Lalalili\CommerceCore\Contracts\OrderLifecycleHook;

class OrderLifecycleHookDispatcher
{
    public function __construct(private readonly Container $container) {}

    public function afterPaid(Model $order): void
    {
        foreach ($this->hooks() as $hook) {
            $hook->afterPaid($order);
        }
    }

    public function beforeCancelled(Model $order): void
    {
        foreach ($this->hooks() as $hook) {
            if ($hook instanceof OrderCancellationLifecycleHook) {
                $hook->beforeCancelled($order);
            }
        }
    }

    public function afterCancelled(Model $order): void
    {
        foreach ($this->hooks() as $hook) {
            $hook->afterCancelled($order);
        }
    }

    public function afterRefunded(Model $order): void
    {
        foreach ($this->hooks() as $hook) {
            $hook->afterRefunded($order);
        }
    }

    public function afterShipped(Model $order): void
    {
        foreach ($this->hooks() as $hook) {
            if ($hook instanceof OrderFulfillmentLifecycleHook) {
                $hook->afterShipped($order);
            }
        }
    }

    public function afterFinished(Model $order): void
    {
        foreach ($this->hooks() as $hook) {
            if ($hook instanceof OrderFulfillmentLifecycleHook) {
                $hook->afterFinished($order);
            }
        }
    }

    /**
     * @return list<OrderLifecycleHook>
     */
    private function hooks(): array
    {
        $configuredHooks = config('commerce.lifecycle.hooks', []);
        if (! is_array($configuredHooks)) {
            return [];
        }

        return array_values(array_map(
            fn (mixed $hook): OrderLifecycleHook => $this->resolveHook($hook),
            $configuredHooks,
        ));
    }

    private function resolveHook(mixed $hook): OrderLifecycleHook
    {
        if (is_string($hook) && class_exists($hook)) {
            $hook = $this->container->make($hook);
        }

        if (! $hook instanceof OrderLifecycleHook) {
            throw new InvalidArgumentException('Commerce lifecycle hooks must implement '.OrderLifecycleHook::class.'.');
        }

        return $hook;
    }
}
