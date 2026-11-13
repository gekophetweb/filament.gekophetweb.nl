<?php

namespace App\Enums;

enum AccessRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
}
