<?php

namespace App\Enum;

enum ReviewStatus: string
{
    case Open = 'open';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
