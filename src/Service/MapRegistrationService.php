<?php

namespace App\Service;

use App\Entity\Map;
use App\Entity\User;
use App\Enum\AuthTokenPurpose;
use App\Enum\MapStatus;
use App\Map\MapSlug;
use App\Repository\MapRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class MapRegistrationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $users,
        private readonly MapRepository $maps,
        private readonly AuthTokenService $authTokens,
        private readonly PlatformMailer $mailer,
    ) {
    }

    /**
     * @throws \DomainException
     */
    public function register(
        string $name,
        string $slug,
        string $ownerEmail,
        float $centerLat,
        float $centerLng,
        float $defaultZoom = 11.0,
        ?string $description = null,
    ): Map {
        $slug = strtolower(trim($slug));
        $name = trim($name);
        $ownerEmail = User::normalizeEmail($ownerEmail);

        if ($name === '' || $ownerEmail === '') {
            throw new \DomainException('Name und E-Mail sind Pflicht.');
        }

        if ($slug === '') {
            $slug = MapSlug::fromTitle($name);
        }
        if ($slug === '' || !MapSlug::isValidFormat($slug)) {
            throw new \DomainException('Slug ist ungültig.');
        }

        $slug = $this->maps->allocateUniqueSlug($slug);

        $user = $this->users->getOrCreate($ownerEmail);
        $map = new Map($slug, $name, $centerLat, $centerLng);
        $map->setDefaultZoom($defaultZoom);
        $map->setDescription($description !== null && trim($description) !== '' ? trim($description) : null);
        $map->setNotifyEmail($ownerEmail);
        $map->setOwner($user);
        $map->setCategoriesConfig([]);
        $map->setStatus(MapStatus::PendingVerify);

        $this->entityManager->persist($map);
        $this->entityManager->flush();

        $issued = $this->authTokens->issue(
            AuthTokenPurpose::MapVerify,
            $user,
            $map,
            AuthTokenService::MAP_VERIFY_TTL,
        );
        $this->mailer->sendMapVerify($user, $map, $issued['token']->getSelector(), $issued['plain']);

        return $map;
    }

    /**
     * @throws \DomainException
     */
    public function verifyAndActivate(string $selector, string $plain): Map
    {
        $token = $this->authTokens->consume($selector, $plain, AuthTokenPurpose::MapVerify);
        $map = $token->getMap();
        if ($map === null) {
            throw new \DomainException('Map zum Token fehlt.');
        }
        if ($map->getStatus() === MapStatus::Disabled) {
            throw new \DomainException('Diese Map ist deaktiviert.');
        }

        $map->activate();
        $this->entityManager->flush();

        return $map;
    }

    /**
     * Issues a magic login mail only when email matches the owner.
     * Returns whether a mail was sent (callers should not reveal this to the client).
     */
    public function requestMagicLogin(Map $map, string $email): bool
    {
        $owner = $map->getOwner();
        if ($owner === null || $map->getStatus() === MapStatus::Disabled) {
            return false;
        }

        if (!hash_equals($owner->getEmail(), User::normalizeEmail($email))) {
            return false;
        }

        $issued = $this->authTokens->issue(
            AuthTokenPurpose::MagicLogin,
            $owner,
            $map,
            AuthTokenService::MAGIC_LOGIN_TTL,
        );
        $this->mailer->sendMagicLogin($owner, $map, $issued['token']->getSelector(), $issued['plain']);

        return true;
    }
}
