<?php

namespace Lalalili\CommerceCore\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceDonation extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('commerce.tables.invoice_donations', 'invoice_donations');
    }
}
