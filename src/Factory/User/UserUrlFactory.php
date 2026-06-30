<?php

namespace App\Factory\User;

use App\Entity\Magazine;
use App\Entity\Message;
use App\Entity\User;
use App\Factory\ActivityPub\PersonFactory;
use App\Factory\Contract\ActorUrlFactory;
use App\Factory\Contract\ContentUrlFactory;
use App\Service\Contracts\SwitchableService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @implements SwitchableService
 * @implements ActorUrlFactory<User>
 */
readonly class UserUrlFactory implements SwitchableService, ActorUrlFactory
{

    public function __construct(
        private PersonFactory $personFactory,
        private UrlGeneratorInterface $urlGenerator,
    ){}

    public function getSupportedTypes(): array
    {
        return [User::class];
    }

    public function getActivityPubId($actor): string
    {
        return $this->personFactory->getActivityPubId($actor);
    }

    public function getLocalUrl($actor): string
    {
        return $this->urlGenerator->generate('user_overview', ['username' => $actor->username], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function getAvatarUrl($actor): ?string
    {
        $slash = $actor->avatar && !str_starts_with('/', $actor->avatar->filePath) ? '/' : '';
        return $actor->avatar ? '/media/cache/resolve/avatar_thumb'.$slash.$actor->avatar->filePath : null;
    }
}
