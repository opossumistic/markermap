<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\RateLimit;

/**
 * IP-based consume helper for anonymous public endpoints.
 */
final class ClientRateLimit
{
    public function enforce(RateLimiterFactoryInterface $factory, Request $request): RateLimit
    {
        $limit = $this->consume($factory, $request);
        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());
            throw new TooManyRequestsHttpException($retryAfter, 'Too many requests. Please try again later.');
        }

        return $limit;
    }

    public function tryConsume(RateLimiterFactoryInterface $factory, Request $request): bool
    {
        return $this->consume($factory, $request)->isAccepted();
    }

    private function consume(RateLimiterFactoryInterface $factory, Request $request): RateLimit
    {
        $key = $request->getClientIp() ?? 'unknown';

        return $factory->create($key)->consume(1);
    }
}
