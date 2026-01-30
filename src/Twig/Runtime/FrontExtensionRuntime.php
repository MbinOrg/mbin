<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
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
        } elseif ('all' !== $content) {
            return 'front_content';
        } else {
            return 'front_short';
        }
    }

    public function getClass(mixed $object): string
    {
        return \get_class($object);
    }

    public function getSubjectType(mixed $object): string
    {
        if ($object instanceof Entry) {
            return 'entry';
        } elseif ($object instanceof EntryComment) {
            return 'entry_comment';
        } elseif ($object instanceof Post) {
            return 'post';
        } elseif ($object instanceof PostComment) {
            return 'post_comment';
        } else {
            throw new \LogicException('unknown class '.\get_class($object));
        }
    }

    public function getNotificationSettingSubjectType(mixed $object): string
    {
        if ($object instanceof Entry) {
            return 'entry';
        } elseif ($object instanceof Post) {
            return 'post';
        } elseif ($object instanceof User) {
            return 'user';
        } elseif ($object instanceof Magazine) {
            return 'magazine';
        } else {
            throw new \LogicException('unknown class '.\get_class($object));
        }
    }
}
