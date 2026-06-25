<?php

namespace Lalalili\CommerceCore\DTOs;

use Illuminate\Database\Eloquent\Model;

class OrderCancellationResult
{
    public function __construct(
        public readonly ?Model $order,
        public readonly bool $transitioned,
        public readonly bool $refunded,
    ) {}
}
