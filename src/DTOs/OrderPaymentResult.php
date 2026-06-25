<?php

namespace Lalalili\CommerceCore\DTOs;

use Illuminate\Database\Eloquent\Model;

class OrderPaymentResult
{
    public function __construct(
        public readonly ?Model $order,
        public readonly bool $transitioned,
    ) {}
}
