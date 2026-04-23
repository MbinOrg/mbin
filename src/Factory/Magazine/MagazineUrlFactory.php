<?php

namespace App\Factory\Magazine;

use App\Entity\Magazine;
use App\Entity\User;
use App\Factory\ActivityPub\GroupFactory;
use App\Factory\Contract\ActorUrlFactory;
use App\Service\Contracts\SwitchableService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @implements SwitchableService
 * @implements ActorUrlFactory<Magazine>
 */
readonly class MagazineUrlFactory implements SwitchableService, ActorUrlFactory
{

    public function __construct(
        private GroupFactory $groupFactory,
        private UrlGeneratorInterface $urlGenerator,
    ){}

    public function getSupportedTypes(): array
    {
        return [Magazine::class];
    }

    public function getActivityPubId($actor): string
    {
        return $this->groupFactory->getActivityPubId($actor);
    }

    public function getLocalUrl($actor): string
    {
        return $this->urlGenerator->generate('front_magazine', ['name' => $actor->name], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function getAvatarUrl($actor): ?string
    {
        $slash = $actor->icon && !str_starts_with('/', $actor->icon->filePath) ? '/' : '';
        return $actor->icon ? '/media/cache/resolve/avatar_thumb'.$slash.$actor->icon->filePath : null;
    }
}
