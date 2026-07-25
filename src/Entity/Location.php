<?php

namespace App\Entity;

use App\Enum\LocationCategory;
use App\Enum\LocationStatus;
use App\Repository\LocationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LocationRepository::class)]
#[ORM\Table(name: 'locations')]
#[ORM\HasLifecycleCallbacks]
class Location
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $street = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $district = null;

    #[ORM\Column(type: Types::FLOAT)]
    private float $lat = 0.0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $lng = 0.0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Stored as list of LocationCategory values (strings) in JSON.
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $categories = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(enumType: LocationStatus::class)]
    private LocationStatus $status = LocationStatus::Pending;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $title = $title !== null ? trim($title) : null;
        $this->title = $title !== '' ? $title : null;

        return $this;
    }

    /**
     * Human-readable label for map/admin when title is empty.
     */
    public function getDisplayLabel(): string
    {
        if ($this->title !== null && $this->title !== '') {
            return $this->title;
        }

        $categoryLabels = array_map(
            static fn (LocationCategory $c) => $c->label(),
            $this->getCategories(),
        );
        $parts = array_filter([
            $categoryLabels !== [] ? implode(', ', $categoryLabels) : 'Tauschbox',
            $this->district,
        ]);

        return implode(' · ', $parts);
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): static
    {
        $this->street = $street;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getDistrict(): ?string
    {
        return $this->district;
    }

    public function setDistrict(?string $district): static
    {
        $this->district = $district;

        return $this;
    }

    public function getLat(): float
    {
        return $this->lat;
    }

    public function setLat(float $lat): static
    {
        $this->lat = $lat;

        return $this;
    }

    public function getLng(): float
    {
        return $this->lng;
    }

    public function setLng(float $lng): static
    {
        $this->lng = $lng;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return list<LocationCategory>
     */
    public function getCategories(): array
    {
        $categories = [];
        foreach ($this->categories as $value) {
            $categories[] = LocationCategory::from((string) $value);
        }

        return $categories;
    }

    /**
     * @param list<LocationCategory|string> $categories
     */
    public function setCategories(array $categories): static
    {
        $values = [];
        foreach ($categories as $category) {
            $values[] = $category instanceof LocationCategory ? $category->value : LocationCategory::from((string) $category)->value;
        }
        $this->categories = array_values(array_unique($values));

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    public function getStatus(): LocationStatus
    {
        return $this->status;
    }

    public function activate(): static
    {
        $this->status = LocationStatus::Active;
        $this->deletedAt = null;

        return $this;
    }

    public function markDisputed(): static
    {
        if ($this->status === LocationStatus::Removed) {
            throw new \DomainException('Removed locations cannot be disputed.');
        }

        $this->status = LocationStatus::Disputed;

        return $this;
    }

    public function softRemove(): static
    {
        $this->status = LocationStatus::Removed;
        $this->deletedAt = new \DateTimeImmutable();

        return $this;
    }

    public function restoreFromDispute(): static
    {
        if ($this->status !== LocationStatus::Disputed) {
            throw new \DomainException('Only disputed locations can be restored to active.');
        }

        $this->status = LocationStatus::Active;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function confirm(\DateTimeImmutable $at = new \DateTimeImmutable()): static
    {
        $this->confirmedAt = $at;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function applyPayload(array $payload): static
    {
        if (array_key_exists('title', $payload)) {
            $this->setTitle($payload['title'] !== null ? (string) $payload['title'] : null);
        }
        if (array_key_exists('street', $payload)) {
            $this->setStreet($payload['street'] !== null ? (string) $payload['street'] : null);
        }
        if (array_key_exists('postal_code', $payload)) {
            $this->setPostalCode($payload['postal_code'] !== null ? (string) $payload['postal_code'] : null);
        }
        if (array_key_exists('district', $payload)) {
            $this->setDistrict($payload['district'] !== null ? (string) $payload['district'] : null);
        }
        if (isset($payload['lat'])) {
            $this->setLat((float) $payload['lat']);
        }
        if (isset($payload['lng'])) {
            $this->setLng((float) $payload['lng']);
        }
        if (array_key_exists('description', $payload)) {
            $this->setDescription($payload['description'] !== null ? (string) $payload['description'] : null);
        }
        if (isset($payload['categories']) && \is_array($payload['categories'])) {
            $this->setCategories($payload['categories']);
        } elseif (isset($payload['category'])) {
            // Backwards-compatible single category from older payloads.
            $this->setCategories([(string) $payload['category']]);
        }
        if (array_key_exists('image_path', $payload)) {
            $this->setImagePath($payload['image_path'] !== null ? (string) $payload['image_path'] : null);
        }

        return $this;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromNewPayload(array $payload): self
    {
        $location = new self();
        $location->applyPayload($payload);
        $location->activate();

        return $location;
    }
}
