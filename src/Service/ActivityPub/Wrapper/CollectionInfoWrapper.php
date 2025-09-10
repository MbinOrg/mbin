<?php

declare(strict_types=1);

namespace App\Service\ActivityPub\Wrapper;

use App\Service\ActivityPub\ContextsProvider;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CollectionInfoWrapper
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ContextsProvider $contextsProvider,
    ) {
    }

    #[ArrayShape([
        '@context' => 'string',
        'type' => 'string',
        'id' => 'string',
        'first' => 'string',
        'totalItems' => 'int',
    ])]
    public function build(string $routeName, array $routeParams, int $count, bool $includeContext = true): array
    {
        $result = [
            '@context' => $this->contextsProvider->referencedContexts(),
            'type' => 'OrderedCollection',
            'id' => $this->urlGenerator->generate($routeName, $routeParams, UrlGeneratorInterface::ABSOLUTE_URL),
            'first' => $this->urlGenerator->generate(
                $routeName,
                $routeParams + ['page' => 1],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'totalItems' => $count,
        ];

        if (!$includeContext) {
            unset($result['@context']);
        }

        return $result;
    }
}
