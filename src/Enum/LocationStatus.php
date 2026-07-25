<?php

namespace App\Enum;

enum LocationStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Disputed = 'disputed';
    case Removed = 'removed';

    public function isVisibleOnMap(): bool
    {
        return match ($this) {
            self::Pending, self::Active, self::Disputed => true,
            default => false,
        };
    }
}
