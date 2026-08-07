<?php

namespace App\Form\Data;

use App\Map\MapSlug;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class CreateMapData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public ?string $name = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 80)]
    #[Assert\Regex(
        pattern: '/^'.MapSlug::PATTERN.'$/',
        message: 'Nur Kleinbuchstaben, Zahlen und Bindestriche (z. B. tischtennis-hh).',
    )]
    public ?string $slug = null;

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

    #[Assert\Callback]
    public function validateSlugReserved(ExecutionContextInterface $context): void
    {
        if ($this->slug !== null && MapSlug::isReserved($this->slug)) {
            $context->buildViolation('Dieser Slug ist reserviert.')
                ->atPath('slug')
                ->addViolation();
        }
    }
}
