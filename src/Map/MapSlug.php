<?php

namespace App\Map;

/**
 * Slug rules and reserved names for /maps/{slug}.
 */
final class MapSlug
{
    public const DEFAULT = 'tauschboxen';

    /** @var list<string> */
    public const RESERVED = [
        'admin',
        'api',
        'new',
        'login',
        'logout',
        'impressum',
        'datenschutz',
        'maps',
        'assets',
        'build',
        'ops',
        '_profiler',
        '_wdt',
    ];

    public const PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';

    private function __construct()
    {
    }

    public static function isReserved(string $slug): bool
    {
        return \in_array(strtolower($slug), self::RESERVED, true);
    }

    public static function isValidFormat(string $slug): bool
    {
        return (bool) preg_match('/^'.self::PATTERN.'$/', $slug);
    }

    /**
     * Title/name → URL slug (de-umlauts, lowercase, hyphenated).
     */
    public static function fromTitle(string $title): string
    {
        $slug = trim($title);
        if ($slug === '') {
            return '';
        }

        $slug = strtr(mb_strtolower($slug, 'UTF-8'), [
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'ß' => 'ss',
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'î' => 'i',
            'ò' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'ù' => 'u',
            'ú' => 'u',
            'û' => 'u',
            'ç' => 'c',
            'ñ' => 'n',
        ]);
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug) ?: $slug;
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug;
    }
}
