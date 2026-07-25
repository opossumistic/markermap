<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Shared hosting: var/data is deploy-excluded and may be missing on first boot.
 * Create it early so SQLite can open/create app.db.
 */
final class EnsureRuntimeDirectoriesSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 1000]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        foreach (['var/data', 'var/data/backups', 'var/tmp', 'public/uploads/locations'] as $relative) {
            $path = $this->projectDir.'/'.$relative;
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
        }
    }
}
