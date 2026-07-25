<?php

namespace App\Enum;

enum LocationCategory: string
{
    case Books = 'books';
    case Toys = 'toys';
    case Clothes = 'clothes';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Books => 'Bücher',
            self::Toys => 'Spielzeug',
            self::Clothes => 'Kleidung',
            self::Other => 'Sonstiges',
        };
    }
}
