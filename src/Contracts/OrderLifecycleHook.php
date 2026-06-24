<?php

namespace Lalalili\CommerceCore\Contracts;

use Illuminate\Database\Eloquent\Model;

interface OrderLifecycleHook
{
    public function afterPaid(Model $order): void;

    public function afterCancelled(Model $order): void;

    public function afterRefunded(Model $order): void;
}
