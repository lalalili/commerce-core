<?php

namespace Lalalili\CommerceCore\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Lalalili\CommerceCore\Contracts\OrderCancellationLifecycleHook;
use Lalalili\CommerceCore\Contracts\OrderLifecycleHook;

class RecordingCancellationLifecycleHook implements OrderCancellationLifecycleHook, OrderLifecycleHook
{
    /**
     * @var list<array{event:string, order_number:string}>
     */
    public static array $events = [];

    public static bool $throwBeforeCancelled = false;

    public function beforeCancelled(Model $order): void
    {
        self::$events[] = [
            'event' => 'before_cancelled',
            'order_number' => (string) data_get($order, 'number'),
        ];

        if (self::$throwBeforeCancelled) {
            throw new \RuntimeException('Cancellation was blocked by the lifecycle hook.');
        }
    }

    public function afterPaid(Model $order): void
    {
        self::$events[] = [
            'event' => 'paid',
            'order_number' => (string) data_get($order, 'number'),
        ];
    }

    public function afterCancelled(Model $order): void
    {
        self::$events[] = [
            'event' => 'cancelled',
            'order_number' => (string) data_get($order, 'number'),
        ];
    }

    public function afterRefunded(Model $order): void
    {
        self::$events[] = [
            'event' => 'refunded',
            'order_number' => (string) data_get($order, 'number'),
        ];
    }

    public static function reset(): void
    {
        self::$events = [];
        self::$throwBeforeCancelled = false;
    }
}
