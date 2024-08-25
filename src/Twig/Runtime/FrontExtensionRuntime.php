<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\RuntimeExtensionInterface;

class FrontExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    // effectively a specialized version of UrlExtensionRuntime::optionsUrl for front routes
    // used for filtering link generation
    public function frontOptionsUrl(
        string $name,
        ?string $value,
        ?string $routeName = null,
        array $additionalParams = [],
    ): string {
        $request = $this->requestStack->getCurrentRequest();
        $attrs = $request->attributes;
        $route = $routeName ?? $attrs->get('_route');

        $params = array_merge($attrs->get('_route_params', []), $request->query->all());
        $params = array_replace($params, $additionalParams);
        $params = array_filter($params, fn ($v) => null !== $v);

        $params[$name] = $value;

        if (str_starts_with($route, 'front') && !str_contains($route, '_magazine')) {
            $route = $this->getFrontRoute($route, $params);
        }

        return $this->urlGenerator->generate($route, $params);
    }

    /**
     * Upgrades shorter `front_*` routes to a front route that can fit all specified params.
     */
    private function getFrontRoute(string $currentRoute, array $params): string
    {
        $content = $params['content'] ?? null;
        $subscription = $params['subscription'] ?? null;

        if ('home' === $subscription) {
            $subscription = null;
        }

        if ($content && $subscription) {
            return 'front';
        } elseif ($subscription) {
            return 'front_sub';
        } elseif ($content) {
            return 'front_content';
        } else {
            return 'front_short';
        }
    }
}
