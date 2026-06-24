<?php

namespace Lalalili\CommerceCore\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Lalalili\CommerceCore\Contracts\OrderFulfillmentLifecycleHook;
use Lalalili\CommerceCore\Contracts\OrderLifecycleHook;

class RecordingLifecycleHook implements OrderFulfillmentLifecycleHook, OrderLifecycleHook
{
    /**
     * @var list<array{event:string, order_number:string}>
     */
    public static array $events = [];

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

    public function afterShipped(Model $order): void
    {
        self::$events[] = [
            'event' => 'shipped',
            'order_number' => (string) data_get($order, 'number'),
        ];
    }

    public function afterFinished(Model $order): void
    {
        self::$events[] = [
            'event' => 'finished',
            'order_number' => (string) data_get($order, 'number'),
        ];
    }

    public static function reset(): void
    {
        self::$events = [];
    }
}
