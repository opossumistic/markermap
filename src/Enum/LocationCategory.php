<?php

namespace App\Enum;

enum LocationCategory: string
{
    case Books = 'books';
    case Toys = 'toys';
    case Clothes = 'clothes';
    case Household = 'household';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Books => 'Bücher & Medien',
            self::Toys => 'Spielzeug & Kinder',
            self::Clothes => 'Kleidung',
            self::Household => 'Haushalt',
            self::Other => 'Sonstiges',
        };
    }
}
