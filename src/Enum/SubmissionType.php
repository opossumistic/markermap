<?php

namespace App\Enum;

enum SubmissionType: string
{
    case New = 'new';
    case Correction = 'correction';
    case StatusReport = 'status_report';
    case Confirmation = 'confirmation';
}
