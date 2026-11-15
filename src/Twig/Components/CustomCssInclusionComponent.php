<?php
declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\User;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('custom_css_inclusion')]
class CustomCssInclusionComponent
{
    public ?string $magazine = null;
    public ?User $profile = null;
    /** @var User[]|null */
    public ?array $contentUsers = null;

    public function styleUsersLocal(): array
    {
        if (null === $this->contentUsers) {
            return [];
        }

        $names = [];
        foreach ($this->contentUsers as $user) {
            if (null !== $user->customPublicContentCss && !$this->isCssRemote($user->customPublicContentCss)) {
                $names[] = $user->getUsername();
            }
        }
        return $names;
    }

    public function styleUsersRemote(): array
    {
        if (null === $this->contentUsers) {
            return [];
        }

        $links = [];
        foreach ($this->contentUsers as $user) {
            if (null !== $user->customPublicContentCss && $this->isCssRemote($user->customPublicContentCss)) {
                $links[] = [
                    'url' => $user->customPublicContentCss,
                    'hash' => $user->customPublicContentCssIntegrity ?? 'null',
                ];
            }
        }
        return $links;
    }

    public function hasLocalProfileStyle(): bool
    {
        return null !== $this->profile?->customPublicProfileCss && !$this->isCssRemote($this->profile->customPublicProfileCss);
    }

    public function hasRemoteProfileStyle(): bool
    {
        return null !== $this->profile?->customPublicProfileCss && $this->isCssRemote($this->profile->customPublicProfileCss);
    }

    private function isCssRemote(string $css): bool
    {
        return str_starts_with($css, 'https://');
    }
}
