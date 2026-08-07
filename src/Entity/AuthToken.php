<?php

namespace App\Entity;

use App\Enum\AuthTokenPurpose;
use App\Repository\AuthTokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthTokenRepository::class)]
#[ORM\Table(name: 'auth_tokens')]
#[ORM\UniqueConstraint(name: 'uniq_auth_tokens_selector', columns: ['selector'])]
#[ORM\Index(name: 'idx_auth_tokens_user', columns: ['user_id'])]
class AuthToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    private string $selector;

    #[ORM\Column(length: 64)]
    private string $verifierHash;

    #[ORM\Column(enumType: AuthTokenPurpose::class)]
    private AuthTokenPurpose $purpose;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Map $map = null;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $consumedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $selector,
        string $verifierHash,
        AuthTokenPurpose $purpose,
        User $user,
        \DateTimeImmutable $expiresAt,
        ?Map $map = null,
    ) {
        $this->selector = $selector;
        $this->verifierHash = $verifierHash;
        $this->purpose = $purpose;
        $this->user = $user;
        $this->map = $map;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSelector(): string
    {
        return $this->selector;
    }

    public function getVerifierHash(): string
    {
        return $this->verifierHash;
    }

    public function getPurpose(): AuthTokenPurpose
    {
        return $this->purpose;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getMap(): ?Map
    {
        return $this->map;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getConsumedAt(): ?\DateTimeImmutable
    {
        return $this->consumedAt;
    }

    public function isConsumed(): bool
    {
        return $this->consumedAt !== null;
    }

    public function isExpired(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        return $this->expiresAt <= $now;
    }

    public function consume(\DateTimeImmutable $at = new \DateTimeImmutable()): void
    {
        if ($this->consumedAt !== null) {
            throw new \DomainException('Token already consumed.');
        }

        $this->consumedAt = $at;
    }
}
