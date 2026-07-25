<?php

namespace App\Map;

use App\Entity\Location;
use App\Enum\LocationCategory;
use App\Enum\LocationStatus;

final class LocationGeoJsonFactory
{
    /**
     * @param list<Location> $locations
     *
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public function featureCollection(array $locations): array
    {
        $features = [];
        foreach ($locations as $location) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$location->getLng(), $location->getLat()],
                ],
                'properties' => $this->properties($location),
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    /**
     * Pending: map shows pin + address only (no UGC title/text/image/categories).
     *
     * @return array<string, mixed>
     */
    private function properties(Location $location): array
    {
        if ($location->getStatus() === LocationStatus::Pending) {
            return [
                'id' => $location->getId(),
                'title' => null,
                'label' => 'Neuer Vorschlag',
                'status' => LocationStatus::Pending->value,
                'categories' => [],
                'category_labels' => [],
                'description' => null,
                'district' => $location->getDistrict(),
                'street' => $location->getStreet(),
                'image_url' => null,
            ];
        }

        return [
            'id' => $location->getId(),
            'title' => $location->getTitle(),
            'label' => $location->getDisplayLabel(),
            'status' => $location->getStatus()->value,
            'categories' => array_map(
                static fn (LocationCategory $c) => $c->value,
                $location->getCategories(),
            ),
            'category_labels' => array_map(
                static fn (LocationCategory $c) => $c->label(),
                $location->getCategories(),
            ),
            'description' => $location->getDescription(),
            'district' => $location->getDistrict(),
            'street' => $location->getStreet(),
            'image_url' => $location->getImagePath() !== null && $location->getImagePath() !== ''
                ? '/'.$location->getImagePath()
                : null,
        ];
    }
}
