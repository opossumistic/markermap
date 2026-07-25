<?php

namespace App\Geo;

/**
 * Official Hamburg Stadtteile (104, since 2011), grouped by Bezirk.
 *
 * @see https://de.wikipedia.org/wiki/Liste_der_Bezirke_und_Stadtteile_Hamburgs
 */
final class HamburgDistricts
{
    /**
     * Common OSM / colloquial names → official Stadtteil.
     * Ambiguous names are omitted (resolve returns null).
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'altstadt' => 'Hamburg-Altstadt',
        'hamburg-altstadt' => 'Hamburg-Altstadt',
        'hamburg altstadt' => 'Hamburg-Altstadt',
        'farmsen' => 'Farmsen-Berne',
        'berne' => 'Farmsen-Berne',
        'farmsen berne' => 'Farmsen-Berne',
        'karolinenviertel' => 'St. Pauli',
        'tegelsbarg' => 'Langenhorn',
        'wohldorf' => 'Wohldorf-Ohlstedt',
        'ohlstedt' => 'Wohldorf-Ohlstedt',
        'neugraben' => 'Neugraben-Fischbek',
        'fischbek' => 'Neugraben-Fischbek',
        'meiendorf' => 'Rahlstedt',
        'neustadt' => 'Neustadt',
    ];

    /**
     * @return array<string, list<string>>
     */
    public static function byBorough(): array
    {
        return [
            'Hamburg-Mitte' => [
                'Hamburg-Altstadt',
                'HafenCity',
                'Neustadt',
                'St. Pauli',
                'St. Georg',
                'Hammerbrook',
                'Borgfelde',
                'Hamm',
                'Horn',
                'Billstedt',
                'Billbrook',
                'Rothenburgsort',
                'Veddel',
                'Wilhelmsburg',
                'Kleiner Grasbrook',
                'Steinwerder',
                'Waltershof',
                'Finkenwerder',
                'Neuwerk',
            ],
            'Altona' => [
                'Altona-Altstadt',
                'Sternschanze',
                'Altona-Nord',
                'Ottensen',
                'Bahrenfeld',
                'Groß Flottbek',
                'Othmarschen',
                'Lurup',
                'Osdorf',
                'Nienstedten',
                'Blankenese',
                'Iserbrook',
                'Sülldorf',
                'Rissen',
            ],
            'Eimsbüttel' => [
                'Eimsbüttel',
                'Rotherbaum',
                'Harvestehude',
                'Hoheluft-West',
                'Lokstedt',
                'Niendorf',
                'Schnelsen',
                'Eidelstedt',
                'Stellingen',
            ],
            'Hamburg-Nord' => [
                'Hoheluft-Ost',
                'Eppendorf',
                'Groß Borstel',
                'Alsterdorf',
                'Winterhude',
                'Uhlenhorst',
                'Hohenfelde',
                'Barmbek-Süd',
                'Dulsberg',
                'Barmbek-Nord',
                'Ohlsdorf',
                'Fuhlsbüttel',
                'Langenhorn',
            ],
            'Wandsbek' => [
                'Eilbek',
                'Wandsbek',
                'Marienthal',
                'Jenfeld',
                'Tonndorf',
                'Farmsen-Berne',
                'Bramfeld',
                'Steilshoop',
                'Wellingsbüttel',
                'Sasel',
                'Poppenbüttel',
                'Hummelsbüttel',
                'Lemsahl-Mellingstedt',
                'Duvenstedt',
                'Wohldorf-Ohlstedt',
                'Bergstedt',
                'Volksdorf',
                'Rahlstedt',
            ],
            'Bergedorf' => [
                'Lohbrügge',
                'Bergedorf',
                'Curslack',
                'Altengamme',
                'Neuengamme',
                'Kirchwerder',
                'Ochsenwerder',
                'Reitbrook',
                'Allermöhe',
                'Billwerder',
                'Moorfleet',
                'Tatenberg',
                'Spadenland',
                'Neuallermöhe',
            ],
            'Harburg' => [
                'Harburg',
                'Neuland',
                'Gut Moor',
                'Wilstorf',
                'Rönneburg',
                'Langenbek',
                'Sinstorf',
                'Marmstorf',
                'Eißendorf',
                'Heimfeld',
                'Moorburg',
                'Altenwerder',
                'Hausbruch',
                'Neugraben-Fischbek',
                'Francop',
                'Neuenfelde',
                'Cranz',
            ],
        ];
    }

    /**
     * Symfony ChoiceType: Bezirk => [label => value].
     *
     * @return array<string, array<string, string>>
     */
    public static function formChoices(): array
    {
        $choices = [];
        foreach (self::byBorough() as $borough => $districts) {
            $group = [];
            foreach ($districts as $name) {
                $group[$name] = $name;
            }
            $choices[$borough] = $group;
        }

        return $choices;
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $all = [];
        foreach (self::byBorough() as $districts) {
            foreach ($districts as $name) {
                $all[] = $name;
            }
        }

        return $all;
    }

    public static function contains(string $name): bool
    {
        return \in_array($name, self::all(), true);
    }

    /**
     * Map free text / Nominatim suburb to an official Stadtteil, or null.
     */
    public static function resolve(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        if (self::contains($trimmed)) {
            return $trimmed;
        }

        $key = self::normalizeKey($trimmed);
        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        foreach (self::all() as $official) {
            if (self::normalizeKey($official) === $key) {
                return $official;
            }
        }

        return null;
    }

    private static function normalizeKey(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
