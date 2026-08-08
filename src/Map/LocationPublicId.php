<?php

namespace App\Map;

/**
 * Immutable public identifier for printed QR / deep links.
 * Alphabet omits 0/O/1/l for sticker readability.
 */
final class LocationPublicId
{
    public const LENGTH = 12;

    public const PATTERN = '[2-9a-km-z]{12}';

    private const ALPHABET = '23456789abcdefghijkmnpqrstuvwxyz';

    private function __construct()
    {
    }

    public static function generate(): string
    {
        $alphabet = self::ALPHABET;
        $max = \strlen($alphabet) - 1;
        $id = '';
        for ($i = 0; $i < self::LENGTH; ++$i) {
            $id .= $alphabet[random_int(0, $max)];
        }

        return $id;
    }

    public static function isValid(string $value): bool
    {
        return (bool) preg_match('/^'.self::PATTERN.'$/', $value);
    }
}
