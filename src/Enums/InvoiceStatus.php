<?php

namespace Lalalili\CommerceCore\Enums;

enum InvoiceStatus: int
{
    case Pending = 0;
    case Complete = 1;
    case Cancelled = 2;
}
