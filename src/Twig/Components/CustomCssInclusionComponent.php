<?php

namespace App\Twig\Components;

use App\Entity\User;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('custom_css_inclusion')]
class CustomCssInclusionComponent
{

    public ?string $magazine = null;
    /** @var User[]|null $contentUsers */
    public ?array $contentUsers = null;

    public function styleUsersLocal(): array
    {
        if($this->contentUsers === null) {
            return [];
        }

        $names = [];
        foreach($this->contentUsers as $user) {
            if($user->customPublicCss !== null && !str_starts_with($user->customPublicCss, 'https://')) {
                $names[] = $user->getUsername();
            }
        }
        return $names;
    }

    public function styleUsersRemote(): array {
        if($this->contentUsers === null) {
            return [];
        }

        $links = [];
        foreach($this->contentUsers as $user) {
            if($user->customPublicCss !== null && str_starts_with($user->customPublicCss, 'https://')) {
                $links[] = [
                    'url' => $user->customPublicCss,
                    'hash' => $user->customPublicCssIntegrity ?? 'null',
                ];
            }
        }
        return $links;
    }
}
