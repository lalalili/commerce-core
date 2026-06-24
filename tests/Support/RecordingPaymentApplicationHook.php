<?php

namespace Lalalili\CommerceCore\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Lalalili\CommerceCore\Contracts\PaymentApplicationHook;
use Lalalili\CommerceCore\DTOs\PaymentApplicationData;

class RecordingPaymentApplicationHook implements PaymentApplicationHook
{
    /**
     * @var list<array{outcome: string, order_number: string, order_exists: bool}>
     */
    public static array $events = [];

    public static function reset(): void
    {
        self::$events = [];
    }

    public function afterApplied(PaymentApplicationData $payment, ?Model $order): void
    {
        self::$events[] = [
            'outcome' => $payment->outcome->value,
            'order_number' => $payment->orderNumber,
            'order_exists' => $order instanceof Model,
        ];
    }
}
