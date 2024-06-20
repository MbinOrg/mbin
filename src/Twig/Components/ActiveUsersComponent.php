<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Magazine;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\SettingsManager;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('active_users')]
final class ActiveUsersComponent
{
    /** @var User[] */
    public array $users = [];

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly CacheInterface $cache,
        private readonly SettingsManager $settingsManager,
    ) {
    }

    public function mount(?Magazine $magazine): void
    {
        $activeUserIds = $this->cache->get("active_users_{$magazine?->getId()}_{$this->settingsManager->getLocale()}",
            function (ItemInterface $item) use ($magazine) {
                $item->expiresAfter(60 * 5); // 5 minutes

                return array_map(fn (User $user) => $user->getId(), $this->userRepository->findActiveUsers($magazine));
            }
        );

        $this->users = $this->userRepository->findBy(['id' => $activeUserIds]);
    }
}
