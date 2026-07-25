<?php

namespace App\Service;

use App\Geo\HamburgDistricts;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Nominatim geocoding (server-side: User-Agent + Hamburg bounding box).
 */
final class ReverseGeocoder
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $userAgent,
        private readonly float $minLat = 53.38,
        private readonly float $maxLat = 53.75,
        private readonly float $minLng = 9.7,
        private readonly float $maxLng = 10.35,
    ) {
    }

    /**
     * @return array{street: ?string, postalCode: ?string, district: ?string, displayName: ?string}|null
     */
    public function reverse(float $lat, float $lng): ?array
    {
        if (!$this->inBounds($lat, $lng)) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', 'https://nominatim.openstreetmap.org/reverse', [
                'query' => [
                    'lat' => $lat,
                    'lon' => $lng,
                    'format' => 'json',
                    'addressdetails' => 1,
                    'zoom' => 18,
                ],
                'headers' => $this->headers(),
                'timeout' => 8,
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray(false);
        } catch (TransportExceptionInterface) {
            return null;
        }

        if (!\is_array($data) || isset($data['error'])) {
            return null;
        }

        return $this->mapResult($data);
    }

    /**
     * Forward geocode: free-text query → candidate points in Hamburg.
     *
     * @return list<array{lat: float, lng: float, street: ?string, postalCode: ?string, district: ?string, displayName: string}>
     */
    public function search(string $query, int $limit = 5): array
    {
        $query = trim($query);
        if ($query === '' || mb_strlen($query) < 3) {
            return [];
        }

        $limit = max(1, min(8, $limit));

        try {
            $response = $this->httpClient->request('GET', 'https://nominatim.openstreetmap.org/search', [
                'query' => [
                    'q' => $query,
                    'format' => 'json',
                    'addressdetails' => 1,
                    'limit' => $limit,
                    'countrycodes' => 'de',
                    // left, top, right, bottom
                    'viewbox' => sprintf('%F,%F,%F,%F', $this->minLng, $this->maxLat, $this->maxLng, $this->minLat),
                    'bounded' => 1,
                ],
                'headers' => $this->headers(),
                'timeout' => 8,
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $rows = $response->toArray(false);
        } catch (TransportExceptionInterface) {
            return [];
        }

        if (!\is_array($rows)) {
            return [];
        }

        $results = [];
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $lat = isset($row['lat']) ? (float) $row['lat'] : null;
            $lng = isset($row['lon']) ? (float) $row['lon'] : null;
            if ($lat === null || $lng === null || !$this->inBounds($lat, $lng)) {
                continue;
            }

            $mapped = $this->mapResult($row);
            if ($mapped === null) {
                continue;
            }

            $results[] = [
                'lat' => $lat,
                'lng' => $lng,
                'street' => $mapped['street'],
                'postalCode' => $mapped['postalCode'],
                'district' => $mapped['district'],
                'displayName' => $mapped['displayName'] ?? sprintf('%F, %F', $lat, $lng),
            ];
        }

        return $results;
    }

    /**
     * @return array{User-Agent: string, Accept-Language: string}
     */
    private function headers(): array
    {
        return [
            'User-Agent' => $this->userAgent,
            'Accept-Language' => 'de',
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{street: ?string, postalCode: ?string, district: ?string, displayName: ?string}|null
     */
    private function mapResult(array $data): ?array
    {
        /** @var array<string, mixed> $address */
        $address = \is_array($data['address'] ?? null) ? $data['address'] : [];

        $road = trim((string) ($address['road'] ?? $address['pedestrian'] ?? $address['path'] ?? ''));
        $house = trim((string) ($address['house_number'] ?? ''));
        $street = trim($road.($house !== '' ? ' '.$house : ''));
        if ($street === '') {
            $street = null;
        }

        $postalCode = isset($address['postcode']) ? (string) $address['postcode'] : null;
        $district = $this->resolveDistrict($address);
        $displayName = isset($data['display_name']) ? (string) $data['display_name'] : null;

        return [
            'street' => $street,
            'postalCode' => $postalCode,
            'district' => $district,
            'displayName' => $displayName,
        ];
    }

    /**
     * @param array<string, mixed> $address
     */
    private function resolveDistrict(array $address): ?string
    {
        foreach (['suburb', 'city_district', 'neighbourhood', 'quarter', 'city_block'] as $key) {
            if (!empty($address[$key]) && \is_string($address[$key])) {
                $resolved = HamburgDistricts::resolve($address[$key]);
                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }

        return null;
    }

    private function inBounds(float $lat, float $lng): bool
    {
        return $lat >= $this->minLat
            && $lat <= $this->maxLat
            && $lng >= $this->minLng
            && $lng <= $this->maxLng;
    }
}
