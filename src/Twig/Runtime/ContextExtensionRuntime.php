<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Repository\Criteria;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\RuntimeExtensionInterface;

class ContextExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function isRouteNameContains(string $needle): bool
    {
        return str_contains($this->getCurrentRouteName(), $needle);
    }

    public function isRouteNameStartsWith(string $needle): bool
    {
        return str_starts_with($this->getCurrentRouteName(), $needle);
    }

    public function isRouteNameEndWith(string $needle): bool
    {
        return str_ends_with($this->getCurrentRouteName(), $needle);
    }

    public function isRouteName(string $needle): bool
    {
        return $this->getCurrentRouteName() === $needle;
    }

    public function isRouteParamsContains(string $paramName, $value): bool
    {
        return $this->requestStack->getMainRequest()->get($paramName) === $value;
    }

    public function routeHasParam(string $name, string $needle): bool
    {
        return $this->requestStack->getCurrentRequest()->get($name) === $needle;
    }

    public function routeParamExists(string $name): bool
    {
        return (bool) $this->requestStack->getCurrentRequest()->get($name);
    }

    private function getCurrentRouteName(): string
    {
        return $this->requestStack->getCurrentRequest()->get('_route') ?? 'front';
    }

    public function getActiveSortOption(): string
    {
        return $this->requestStack->getCurrentRequest()->get('sortBy') ?? 'hot';
    }

    public function getRouteParam(string $name): ?string
    {
        return $this->requestStack->getCurrentRequest()->get($name);
    }

    public function getTimeParamTranslated(): string
    {
        $paramValue = $this->getRouteParam('time');
        if (!\in_array($paramValue, Criteria::TIME_ROUTES_EN)
            || '∞' === $paramValue
            || 'all' === $paramValue
        ) {
            return $this->translator->trans('all_time');
        }

        return $this->translator->trans($paramValue);
    }
}
