<?php

namespace App\Entity;

use App\Enum\MapStatus;
use App\Repository\MapRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MapRepository::class)]
#[ORM\Table(name: 'maps')]
#[ORM\UniqueConstraint(name: 'uniq_maps_slug', columns: ['slug'])]
#[ORM\HasLifecycleCallbacks]
class Map
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $slug;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::FLOAT)]
    private float $centerLat;

    #[ORM\Column(type: Types::FLOAT)]
    private float $centerLng;

    #[ORM\Column(type: Types::FLOAT)]
    private float $defaultZoom = 11.0;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $minLat = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $maxLat = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $minLng = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $maxLng = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $notifyEmail = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $owner = null;

    /**
     * Optional category values for this map.
     * null or [] = no category UI (generic maps).
     * non-empty = show those enum values (Tauschboxen).
     *
     * @var list<string>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $categoriesConfig = null;

    #[ORM\Column(enumType: MapStatus::class)]
    private MapStatus $status = MapStatus::PendingVerify;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $slug, string $name, float $centerLat, float $centerLng)
    {
        $this->slug = $slug;
        $this->name = $name;
        $this->centerLat = $centerLat;
        $this->centerLng = $centerLng;
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getCenterLat(): float
    {
        return $this->centerLat;
    }

    public function setCenterLat(float $centerLat): static
    {
        $this->centerLat = $centerLat;

        return $this;
    }

    public function getCenterLng(): float
    {
        return $this->centerLng;
    }

    public function setCenterLng(float $centerLng): static
    {
        $this->centerLng = $centerLng;

        return $this;
    }

    public function getDefaultZoom(): float
    {
        return $this->defaultZoom;
    }

    public function setDefaultZoom(float $defaultZoom): static
    {
        $this->defaultZoom = $defaultZoom;

        return $this;
    }

    public function getMinLat(): ?float
    {
        return $this->minLat;
    }

    public function getMaxLat(): ?float
    {
        return $this->maxLat;
    }

    public function getMinLng(): ?float
    {
        return $this->minLng;
    }

    public function getMaxLng(): ?float
    {
        return $this->maxLng;
    }

    public function setBounds(?float $minLat, ?float $maxLat, ?float $minLng, ?float $maxLng): static
    {
        $this->minLat = $minLat;
        $this->maxLat = $maxLat;
        $this->minLng = $minLng;
        $this->maxLng = $maxLng;

        return $this;
    }

    public function hasBounds(): bool
    {
        return $this->minLat !== null
            && $this->maxLat !== null
            && $this->minLng !== null
            && $this->maxLng !== null;
    }

    /**
     * @return array{minLat: float, maxLat: float, minLng: float, maxLng: float}|null
     */
    public function getBounds(): ?array
    {
        if (!$this->hasBounds()) {
            return null;
        }

        return [
            'minLat' => $this->minLat,
            'maxLat' => $this->maxLat,
            'minLng' => $this->minLng,
            'maxLng' => $this->maxLng,
        ];
    }

    public function containsCoordinates(float $lat, float $lng): bool
    {
        if (!$this->hasBounds()) {
            return true;
        }

        return $lat >= $this->minLat
            && $lat <= $this->maxLat
            && $lng >= $this->minLng
            && $lng <= $this->maxLng;
    }

    public function getNotifyEmail(): ?string
    {
        return $this->notifyEmail;
    }

    public function setNotifyEmail(?string $notifyEmail): static
    {
        $this->notifyEmail = $notifyEmail;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner !== null && $this->owner->getId() === $user->getId();
    }

    /**
     * @return list<string>|null
     */
    public function getCategoriesConfig(): ?array
    {
        return $this->categoriesConfig;
    }

    /**
     * @param list<string>|null $categoriesConfig
     */
    public function setCategoriesConfig(?array $categoriesConfig): static
    {
        $this->categoriesConfig = $categoriesConfig;

        return $this;
    }

    public function usesCategories(): bool
    {
        return \is_array($this->categoriesConfig) && $this->categoriesConfig !== [];
    }

    public function getStatus(): MapStatus
    {
        return $this->status;
    }

    public function setStatus(MapStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function activate(): static
    {
        $this->status = MapStatus::Active;

        return $this;
    }

    public function disable(): static
    {
        $this->status = MapStatus::Disabled;

        return $this;
    }

    public function isPubliclyAccessible(): bool
    {
        return $this->status->isPubliclyAccessible();
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
     * Hamburg Tauschboxen defaults (Tenant #1 seed).
     */
    public static function createTauschboxenDefault(): self
    {
        $map = new self(
            \App\Map\MapSlug::DEFAULT,
            'Tauschboxen Hamburg',
            53.5511,
            9.9937,
        );
        $map->setDefaultZoom(11.0);
        $map->setBounds(53.38, 53.75, 9.7, 10.35);
        $map->setDescription('Öffentliche Tauschboxen in Hamburg — vorschlagen, ergänzen, melden.');
        $map->setCategoriesConfig(array_map(
            static fn (\App\Enum\LocationCategory $c) => $c->value,
            \App\Enum\LocationCategory::cases(),
        ));
        $map->activate();

        return $map;
    }
}
