<?php

namespace Lalalili\CommerceCore\Enums;

enum InvoiceType: int
{
    case Duplicate = 1;
    case Donation = 2;
    case Triplicate = 3;
    case DuplicateMember = 4;
    case DuplicateCertification = 5;
    case DuplicateMobile = 6;
}
