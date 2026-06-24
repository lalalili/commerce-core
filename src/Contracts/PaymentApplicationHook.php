<?php

namespace Lalalili\CommerceCore\Contracts;

use Illuminate\Database\Eloquent\Model;
use Lalalili\CommerceCore\DTOs\PaymentApplicationData;

interface PaymentApplicationHook
{
    public function afterApplied(PaymentApplicationData $payment, ?Model $order): void;
}
