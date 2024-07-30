<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Twig\Runtime\MagazineExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MagazineExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_magazine_subscribed', [MagazineExtensionRuntime::class, 'isSubscribed']),
            new TwigFunction('is_magazine_blocked', [MagazineExtensionRuntime::class, 'isBlocked']),
            new TwigFunction('magazine_has_local_subscribers', [MagazineExtensionRuntime::class, 'hasLocalSubscribers']),
            new TwigFunction('get_instance_of_magazine', [MagazineExtensionRuntime::class, 'getInstanceOfMagazine']),
        ];
    }
}
