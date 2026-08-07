<?php

namespace App\Form\Data;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateMapData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public ?string $name = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\Length(max: 500)]
    public ?string $description = null;

    #[Assert\NotNull]
    #[Assert\Range(min: -90, max: 90)]
    public ?float $centerLat = 51.1657;

    #[Assert\NotNull]
    #[Assert\Range(min: -180, max: 180)]
    public ?float $centerLng = 10.4515;

    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 18)]
    public ?float $defaultZoom = 6.0;

    /** Honeypot — must stay empty. */
    public ?string $website = null;
}
