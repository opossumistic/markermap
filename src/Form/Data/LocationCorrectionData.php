<?php

namespace App\Form\Data;

use App\Entity\Location;
use App\Enum\LocationCategory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

final class LocationCorrectionData
{
    #[Assert\NotNull]
    #[Assert\Positive]
    public ?int $locationId = null;

    #[Assert\Length(max: 180)]
    public ?string $title = null;

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    /** @var list<LocationCategory> */
    public array $categories = [];

    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    /** Honeypot */
    public ?string $website = null;

    #[Assert\Image(
        maxSize: '2M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        mimeTypesMessage: 'Bitte ein JPEG-, PNG- oder WebP-Bild hochladen (max. 2 MB).',
    )]
    public ?UploadedFile $image = null;

    /**
     * Full editable snapshot (edit model). Image is attached by the controller.
     *
     * @return array{title: ?string, description: ?string, categories: list<string>}
     */
    public function toPayload(): array
    {
        $title = $this->title !== null ? trim($this->title) : '';
        $description = $this->description !== null ? trim($this->description) : '';

        return [
            'title' => $title !== '' ? $title : null,
            'description' => $description !== '' ? $description : null,
            'categories' => array_map(
                static fn (LocationCategory $c) => $c->value,
                $this->categories,
            ),
        ];
    }

    public function hasContent(): bool
    {
        $payload = $this->toPayload();

        return $payload['title'] !== null
            || $payload['description'] !== null
            || $payload['categories'] !== []
            || $this->image !== null;
    }

    /**
     * Only keys that actually differ from the live location (avoids no-op moderation).
     *
     * @param array{title: ?string, description: ?string, categories: list<string>} $payload
     *
     * @return array<string, mixed>
     */
    public function diffAgainstLocation(Location $location, array $payload): array
    {
        $diff = [];

        $newTitle = $payload['title'] ?? null;
        if ($newTitle !== $location->getTitle()) {
            $diff['title'] = $newTitle;
        }

        $newDescription = $payload['description'] ?? null;
        if ($newDescription !== $location->getDescription()) {
            $diff['description'] = $newDescription;
        }

        $currentCategories = array_map(
            static fn (LocationCategory $c) => $c->value,
            $location->getCategories(),
        );
        $newCategories = $payload['categories'] ?? [];
        sort($currentCategories);
        $sortedNew = $newCategories;
        sort($sortedNew);
        if ($sortedNew !== $currentCategories) {
            $diff['categories'] = $newCategories;
        }

        return $diff;
    }
}
