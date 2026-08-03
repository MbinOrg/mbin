<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Entity\User;
use App\Repository\Criteria;
use App\Utils\Polyfills;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\RuntimeExtensionInterface;

class ContextExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
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
        return Polyfills::requestParam($this->requestStack->getCurrentRequest(), $name) === $needle;
    }

    public function routeParamExists(string $name): bool
    {
        return (bool) Polyfills::requestParam($this->requestStack->getCurrentRequest(), $name);
    }

    private function getCurrentRouteName(): string
    {
        return Polyfills::requestParam($this->requestStack->getCurrentRequest(), '_route') ?? 'front';
    }

    public function getActiveSortOption(): string
    {
        $defaultSort = $this->getDefaultSortOption();
        $requestSort = Polyfills::requestParam($this->requestStack->getCurrentRequest(), 'sortBy');

        return 'default' !== $requestSort ? ($requestSort ?? $defaultSort) : $defaultSort;
    }

    public function getDefaultSortOption(): string
    {
        $defaultSort = 'hot';
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $defaultSort = $user->frontDefaultSort;
        }

        return $defaultSort;
    }

    public function getActiveSortOptionForComments(): string
    {
        $defaultSort = $this->getDefaultSortOptionForComments();
        $requestSort = Polyfills::requestParam($this->requestStack->getCurrentRequest(), 'sortBy');

        return 'default' !== $requestSort ? ($requestSort ?? $defaultSort) : $defaultSort;
    }

    public function getDefaultSortOptionForComments(): string
    {
        $defaultSort = 'hot';
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $defaultSort = $user->commentDefaultSort;
        }

        return $defaultSort;
    }

    public function getRouteParam(string $name): ?string
    {
        return Polyfills::requestParam($this->requestStack->getCurrentRequest(), $name);
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

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now');
    }
}
