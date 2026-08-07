<?php

namespace App\Http;

use App\Entity\Map;
use App\Repository\MapRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves route param {mapSlug} to an active {@see Map} and stores it on the request.
 */
final class MapValueResolver implements ValueResolverInterface
{
    public const REQUEST_ATTRIBUTE = '_current_map';

    public function __construct(
        private readonly MapRepository $maps,
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ($argument->getType() !== Map::class) {
            return [];
        }

        $slug = $request->attributes->get('mapSlug');
        if (!\is_string($slug) || $slug === '') {
            return [];
        }

        $map = str_contains($request->getPathInfo(), '/admin')
            ? $this->maps->findOneBySlug($slug)
            : $this->maps->findActiveBySlug($slug);

        if ($map === null) {
            throw new NotFoundHttpException(sprintf('Map "%s" not found.', $slug));
        }

        $request->attributes->set(self::REQUEST_ATTRIBUTE, $map);

        yield $map;
    }
}
