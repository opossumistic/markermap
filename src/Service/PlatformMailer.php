<?php

namespace App\Service;

use App\Entity\Map;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Platform mails: map verify + magic login (sync, shared hosting).
 */
final class PlatformMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $from,
    ) {
    }

    public function sendMapVerify(User $user, Map $map, string $selector, string $plain): void
    {
        $url = $this->urlGenerator->generate('auth_map_verify', [
            'selector' => $selector,
            'token' => $plain,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->send(
            to: $user->getEmail(),
            subject: sprintf('Markermap: „%s“ bestätigen', $map->getName()),
            template: 'email/map_verify.txt.twig',
            context: [
                'map' => $map,
                'verifyUrl' => $url,
            ],
        );
    }

    public function sendMagicLogin(User $user, Map $map, string $selector, string $plain): void
    {
        $url = $this->urlGenerator->generate('auth_magic_login', [
            'selector' => $selector,
            'token' => $plain,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->send(
            to: $user->getEmail(),
            subject: sprintf('Markermap: Login für „%s“', $map->getName()),
            template: 'email/magic_login.txt.twig',
            context: [
                'map' => $map,
                'loginUrl' => $url,
            ],
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send(string $to, string $subject, string $template, array $context): void
    {
        $message = (new TemplatedEmail())
            ->from(Address::create($this->from))
            ->to($to)
            ->subject($subject)
            ->textTemplate($template)
            ->context($context);

        try {
            $this->mailer->send($message);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Platform mail failed.', [
                'to' => $to,
                'subject' => $subject,
                'exception' => $e,
            ]);
        }
    }
}
