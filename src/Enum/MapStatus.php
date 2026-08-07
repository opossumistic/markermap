<?php

namespace App\Enum;

enum MapStatus: string
{
    case PendingVerify = 'pending_verify';
    case Active = 'active';
    case Disabled = 'disabled';

    public function isPubliclyAccessible(): bool
    {
        return $this === self::Active;
    }
}
