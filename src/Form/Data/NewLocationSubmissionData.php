<?php

namespace App\Form\Data;

use App\Enum\LocationCategory;
use App\Validation\LocationFieldLimits;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

final class NewLocationSubmissionData
{
    #[Assert\Length(max: 180)]
    public ?string $title = null;

    #[Assert\Length(max: 180)]
    public ?string $street = null;

    /** Worldwide postcodes (DE 5 digits, UK/CA alphanumerics, etc.). Empty allowed. */
    #[Assert\Length(max: 10)]
    #[Assert\Regex(
        pattern: '/^(?:[A-Za-z0-9][A-Za-z0-9 \-]{0,9})?$/',
        message: 'PLZ/Postcode bitte prüfen (Buchstaben, Ziffern, Leerzeichen oder Bindestrich).',
    )]
    public ?string $postalCode = null;

    #[Assert\Length(
        max: LocationFieldLimits::DESCRIPTION_MAX,
        maxMessage: 'Bitte höchstens {{ limit }} Zeichen.',
    )]
    public ?string $description = null;

    /** @var list<LocationCategory> */
    public array $categories = [];

    #[Assert\NotNull]
    #[Assert\Range(min: -90, max: 90, notInRangeMessage: 'Ungültige Latitude.')]
    public ?float $lat = null;

    #[Assert\NotNull]
    #[Assert\Range(min: -180, max: 180, notInRangeMessage: 'Ungültige Longitude.')]
    public ?float $lng = null;

    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    /** Honeypot — must stay empty. */
    public ?string $website = null;

    #[Assert\Image(
        maxSize: LocationFieldLimits::IMAGE_MAX_SIZE,
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        mimeTypesMessage: 'Bitte ein JPEG-, PNG- oder WebP-Bild hochladen (max. '.LocationFieldLimits::IMAGE_MAX_SIZE_LABEL.').',
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
