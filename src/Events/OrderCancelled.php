<?php

namespace Lalalili\CommerceCore\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * 訂單取消、狀態實際轉移後（commit 後）派發；已取消的訂單重呼不重複派發。
 */
final class OrderCancelled
{
    public function __construct(
        public readonly Model $order,
        public readonly ?int $updatedBy = null,
    ) {}
}
