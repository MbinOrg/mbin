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
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function navbarThreadsUrl(?Magazine $magazine): string
    {
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

        if (str_starts_with($this->getCurrentRouteName(), 'tag')) {
            return $this->urlGenerator->generate(
                'tag_entries',
                ['name' => $this->requestStack->getCurrentRequest()->get('name')]
            );
        }

        if (str_ends_with($this->getCurrentRouteName(), '_subscribed')) {
            return $this->urlGenerator->generate('front_subscribed', $this->getActiveOptions());
        }

        if (str_ends_with($this->getCurrentRouteName(), '_favourite')) {
            return $this->urlGenerator->generate('front_favourite', $this->getActiveOptions());
        }

        if (str_ends_with($this->getCurrentRouteName(), '_moderated')) {
            return $this->urlGenerator->generate('front_moderated', $this->getActiveOptions());
        }

        return $this->urlGenerator->generate('front', $this->getActiveOptions());
    }

    public function navbarPostsUrl(?Magazine $magazine): string
    {
        if ($magazine instanceof Magazine) {
            return $this->urlGenerator->generate('magazine_posts', [
                'name' => $magazine->name,
                ...$this->getActiveOptions(),
            ]);
        }

        if (str_starts_with($this->getCurrentRouteName(), 'tag')) {
            return $this->urlGenerator->generate(
                'tag_posts',
                ['name' => $this->requestStack->getCurrentRequest()->get('name')]
            );
        }

        if (str_ends_with($this->getCurrentRouteName(), '_subscribed')) {
            return $this->urlGenerator->generate('posts_subscribed', $this->getActiveOptions());
        }

        if (str_ends_with($this->getCurrentRouteName(), '_favourite')) {
            return $this->urlGenerator->generate('posts_favourite', $this->getActiveOptions());
        }

        if (str_ends_with($this->getCurrentRouteName(), '_moderated')) {
            return $this->urlGenerator->generate('posts_moderated', $this->getActiveOptions());
        }

        return $this->urlGenerator->generate('posts_front', $this->getActiveOptions());
    }

    public function navbarPeopleUrl(?Magazine $magazine): string
    {
        if (str_starts_with($this->getCurrentRouteName(), 'tag')) {
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
        $sortOption = $this->getActiveSortOption();
        $timeOption = $this->getActiveTimeOption();
        $options = [];

        // don't add the current options if they are the defaults
        if ('hot' !== $sortOption) {
            $options['sortBy'] = $sortOption;
        }
        if ('∞' !== $timeOption) {
            $options['time'] = $timeOption;
        }

        return $options;
    }

    private function getActiveSortOption(): string
    {
        return $this->requestStack->getCurrentRequest()->get('sortBy') ?? 'hot';
    }

    private function getActiveTimeOption(): string
    {
        return $this->requestStack->getCurrentRequest()->get('time') ?? '∞';
    }
}
