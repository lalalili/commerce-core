<?php

namespace Lalalili\CommerceCore\Contracts;

use Illuminate\Database\Eloquent\Model;

interface OrderFulfillmentLifecycleHook
{
    public function afterShipped(Model $order): void;

    public function afterFinished(Model $order): void;
}
