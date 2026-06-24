<?php

namespace Lalalili\CommerceCore\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * 訂單完成後派發；idempotent 重呼不重複派發。
 */
final class OrderFinished
{
    public function __construct(
        public readonly Model $order,
        public readonly int|string|null $updatedBy = null,
    ) {}
}
