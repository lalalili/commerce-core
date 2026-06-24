<?php

namespace Lalalili\CommerceCore\Enums;

enum PaymentApplicationOutcome: string
{
    case Paid = 'paid';
    case Pending = 'pending';
    case Declined = 'declined';
    case Refunded = 'refunded';
    case UserCancelled = 'user_cancelled';
    case QueryFailed = 'query_failed';
}
