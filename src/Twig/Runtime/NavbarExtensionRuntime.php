<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Entity\Magazine;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\RuntimeExtensionInterface;

class NavbarExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly FrontExtensionRuntime $frontExtension,
    ) {
    }

    public function navbarThreadsUrl(?Magazine $magazine): string
    {
        if ($this->isRouteNameStartsWith('front')) {
            return $this->frontExtension->frontOptionsUrl(
                'content', 'threads',
                $magazine instanceof Magazine ? 'front_magazine' : 'front',
                ['name' => $magazine?->name, 'p' => null],
            );
        }

        if ($magazine instanceof Magazine) {
            return $this->urlGenerator->generate('front_magazine', [
                'name' => $magazine->name,
                ...$this->getActiveOptions(),
            ]);
        }

        if ($domain = $this->requestStack->getCurrentRequest()->get('domain')) {
            return $this->urlGenerator->generate('domain_entries', [
                'name' => $domain->name,
                ...$this->getActiveOptions(),
            ]);
        }

        if ($this->isRouteNameStartsWith('tag')) {
            return $this->urlGenerator->generate(
                'tag_entries',
                ['name' => $this->requestStack->getCurrentRequest()->get('name')]
            );
        }

        return $this->urlGenerator->generate('front', $this->getActiveOptions());
    }

    public function navbarCombinedUrl(?Magazine $magazine): string
    {
        if ($this->isRouteNameStartsWith('front')) {
            return $this->frontExtension->frontOptionsUrl(
                'content', 'combined',
                $magazine instanceof Magazine ? 'front_magazine' : 'front',
                ['name' => $magazine?->name, 'p' => null],
            );
        }

        if ($magazine instanceof Magazine) {
            return $this->urlGenerator->generate('front_magazine', [
                'name' => $magazine->name,
                ...$this->getActiveOptions(),
                'content' => 'combined',
            ]);
        }

        if ($domain = $this->requestStack->getCurrentRequest()->get('domain')) {
            return $this->urlGenerator->generate('domain_entries', [
                'name' => $domain->name,
                ...$this->getActiveOptions(),
            ]);
        }

        if ($this->isRouteNameStartsWith('tag')) {
            return $this->urlGenerator->generate(
                'tag_entries',
                ['name' => $this->requestStack->getCurrentRequest()->get('name')]
            );
        }

        return $this->urlGenerator->generate('front', $this->getActiveOptions());
    }

    public function navbarPostsUrl(?Magazine $magazine): string
    {
        if ($this->isRouteNameStartsWith('front')) {
            return $this->frontExtension->frontOptionsUrl(
                'content', 'microblog',
                $magazine instanceof Magazine ? 'front_magazine' : 'front',
                ['name' => $magazine?->name, 'p' => null, 'type' => null],
            );
        }

        if ($magazine instanceof Magazine) {
            return $this->urlGenerator->generate('magazine_posts', [
                'name' => $magazine->name,
                ...$this->getActiveOptions(),
            ]);
        }

        if ($this->isRouteNameStartsWith('tag')) {
            return $this->urlGenerator->generate(
                'tag_posts',
                ['name' => $this->requestStack->getCurrentRequest()->get('name')]
            );
        }

        if ($this->isRouteNameEndWith('_subscribed')) {
            return $this->urlGenerator->generate('posts_subscribed', $this->getActiveOptions());
        }

        if ($this->isRouteNameEndWith('_favourite')) {
            return $this->urlGenerator->generate('posts_favourite', $this->getActiveOptions());
        }

        if ($this->isRouteNameEndWith('_moderated')) {
            return $this->urlGenerator->generate('posts_moderated', $this->getActiveOptions());
        }

        return $this->urlGenerator->generate('posts_front', $this->getActiveOptions());
    }

    public function navbarPeopleUrl(?Magazine $magazine): string
    {
        if ($this->isRouteNameStartsWith('tag')) {
            return $this->urlGenerator->generate(
                'tag_people',
                ['name' => $this->requestStack->getCurrentRequest()->get('name')]
            );
        }

        if ($magazine instanceof Magazine) {
            return $this->urlGenerator->generate('magazine_people', ['name' => $magazine->name]);
        }

        return $this->urlGenerator->generate('people_front');
    }

    private function getCurrentRouteName(): string
    {
        return $this->requestStack->getCurrentRequest()->get('_route') ?? 'front';
    }

    private function getActiveOptions(): array
    {
        $options = [];

        // don't use sortBy or time options on comment pages
        // for the navbar links, so sorting comments by new does not mean
        // changing the entry and microblog views to newest
        if (!$this->isRouteName('root')
            && !$this->isRouteNameStartsWith('front')
            && !$this->isRouteNameStartsWith('posts')
            && !$this->isRouteName('magazine_posts')
        ) {
            return $options;
        }

        $sortOption = $this->getActiveSortOption();
        $timeOption = $this->getActiveTimeOption();
        $subscriptionOption = $this->getActiveSubscriptionOption();
        $contentOption = $this->getActiveContentOption();

        // don't add the current options if they are the defaults.
        // this isn't bad, but keeps urls shorter for instance
        // showing /microblog rather than /microblog/hot/∞
        // which would be equivalent anyways
        if ('hot' !== $sortOption) {
            $options['sortBy'] = $sortOption;
        }
        if ('∞' !== $timeOption) {
            $options['time'] = $timeOption;
        }
        if ('default' !== $contentOption) {
            $options['content'] = $contentOption;
        }
        if (!\in_array($subscriptionOption, [null, 'home'])) {
            $options['subscription'] = $subscriptionOption;
        }

        return $options;
    }

    private function getActiveSubscriptionOption(): ?string
    {
        return $this->requestStack->getCurrentRequest()->get('subscription');
    }

    private function getActiveSortOption(): string
    {
        return $this->requestStack->getCurrentRequest()->get('sortBy') ?? 'hot';
    }

    private function getActiveTimeOption(): string
    {
        return $this->requestStack->getCurrentRequest()->get('time') ?? '∞';
    }

    private function getActiveContentOption(): string
    {
        return $this->requestStack->getCurrentRequest()->get('content') ?? 'default';
    }

    private function isRouteNameStartsWith(string $needle): bool
    {
        return str_starts_with($this->getCurrentRouteName(), $needle);
    }

    private function isRouteNameEndWith(string $needle): bool
    {
        return str_ends_with($this->getCurrentRouteName(), $needle);
    }

    private function isRouteName(string $needle): bool
    {
        return $this->getCurrentRouteName() === $needle;
    }
}
