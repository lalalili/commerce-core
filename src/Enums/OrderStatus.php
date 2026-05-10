<?php

namespace Lalalili\CommerceCore\Enums;

enum OrderStatus: int
{
    case Pending = 0;
    case Complete = 1;
    case Shipping = 2;
    case Finished = 3;
    case Cancelled = 4;
}
