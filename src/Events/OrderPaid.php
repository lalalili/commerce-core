<?php

namespace Lalalili\CommerceCore\Events;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * 訂單付款完成、狀態實際轉移後（commit 後）派發；idempotent 重呼不重複派發。
 */
final class OrderPaid
{
    public function __construct(
        public readonly Model $order,
        public readonly string $paymentStatusMessage,
        public readonly CarbonInterface $paymentTime,
        public readonly int|string|null $updatedBy = null,
    ) {}
}
