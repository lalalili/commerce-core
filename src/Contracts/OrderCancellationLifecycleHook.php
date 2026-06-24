<?php

namespace Lalalili\CommerceCore\Contracts;

use Illuminate\Database\Eloquent\Model;

interface OrderCancellationLifecycleHook
{
    public function beforeCancelled(Model $order): void;
}
