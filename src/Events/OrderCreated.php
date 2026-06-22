<?php

namespace Lalalili\CommerceCore\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * 訂單建立後（commit 後）派發。
 */
final class OrderCreated
{
    public function __construct(
        public readonly Model $order,
    ) {}
}
