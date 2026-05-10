<?php

namespace Lalalili\CommerceCore\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('commerce.tables.payment_logs', 'payment_logs');
    }

    public function casts(): array
    {
        return [
            'response' => 'array',
        ];
    }
}
