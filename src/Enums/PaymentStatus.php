<?php

namespace Lalalili\CommerceCore\Enums;

enum PaymentStatus: int
{
    case Pending = 0;
    case Complete = 1;
    case Failed = 2;
    case Cancelled = 3;
    case Refunded = 4;
}
