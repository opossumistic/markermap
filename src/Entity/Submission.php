<?php

namespace App\Entity;

use App\Enum\ReviewStatus;
use App\Enum\SubmissionType;
use App\Repository\SubmissionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubmissionRepository::class)]
#[ORM\Table(name: 'submissions')]
class Submission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Map $map;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Location $location = null;

    #[ORM\Column(enumType: SubmissionType::class)]
    private SubmissionType $type;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\Column(enumType: ReviewStatus::class)]
    private ReviewStatus $reviewStatus = ReviewStatus::Open;

    public function __construct(
        SubmissionType $type,
        array $payload = [],
        ?Location $location = null,
        ?string $email = null,
        ?Map $map = null,
    ) {
        $this->type = $type;
        $this->payload = $payload;
        $this->location = $location;
        $this->email = $email;
        $resolved = $map ?? $location?->getMap();
        if ($resolved === null) {
            throw new \InvalidArgumentException('Submission requires a Map (or a Location that belongs to one).');
        }
        $this->map = $resolved;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getMap(): Map
    {
        return $this->map;
    }

    public function setMap(Map $map): static
    {
        $this->map = $map;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function setLocation(?Location $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getType(): SubmissionType
    {
        return $this->type;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function getReviewStatus(): ReviewStatus
    {
        return $this->reviewStatus;
    }

    public function approve(\DateTimeImmutable $at = new \DateTimeImmutable()): static
    {
        if ($this->reviewStatus !== ReviewStatus::Open) {
            throw new \DomainException('Only open submissions can be approved.');
        }

        $this->reviewStatus = ReviewStatus::Approved;
        $this->reviewedAt = $at;

        return $this;
    }

    public function reject(\DateTimeImmutable $at = new \DateTimeImmutable()): static
    {
        if ($this->reviewStatus !== ReviewStatus::Open) {
            throw new \DomainException('Only open submissions can be rejected.');
        }

        $this->reviewStatus = ReviewStatus::Rejected;
        $this->reviewedAt = $at;

        return $this;
    }

    public function isOpen(): bool
    {
        return $this->reviewStatus === ReviewStatus::Open;
    }
}
