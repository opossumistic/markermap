<?php

namespace App\Form\Data;

use App\Enum\LocationCategory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

final class NewLocationSubmissionData
{
    #[Assert\Length(max: 180)]
    public ?string $title = null;

    #[Assert\Length(max: 180)]
    public ?string $street = null;

    #[Assert\Length(max: 10)]
    #[Assert\Regex(pattern: '/^\d{5}$/', message: 'Bitte eine 5-stellige PLZ angeben.')]
    public ?string $postalCode = null;

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    /** @var list<LocationCategory> */
    #[Assert\Count(min: 1, minMessage: 'Bitte mindestens eine Kategorie wählen.')]
    public array $categories = [];

    #[Assert\NotNull]
    #[Assert\Range(min: 53.38, max: 53.75, notInRangeMessage: 'Koordinaten müssen in Hamburg liegen.')]
    public ?float $lat = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 9.70, max: 10.35, notInRangeMessage: 'Koordinaten müssen in Hamburg liegen.')]
    public ?float $lng = null;

    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    /** Honeypot — must stay empty. */
    public ?string $website = null;

    #[Assert\Image(
        maxSize: '2M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        mimeTypesMessage: 'Bitte ein JPEG-, PNG- oder WebP-Bild hochladen (max. 2 MB).',
    )]
    public ?UploadedFile $image = null;

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'title' => $this->title,
            'street' => $this->street,
            'postal_code' => $this->postalCode,
            'description' => $this->description,
            'categories' => array_map(
                static fn (LocationCategory $c) => $c->value,
                $this->categories,
            ),
            'lat' => $this->lat,
            'lng' => $this->lng,
        ];
    }
}
