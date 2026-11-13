<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\UserSettingsDto;
use App\DTO\UserStylesDto;
use App\Entity\User;
use App\Enums\EDirectMessageSettings;
use App\Enums\EFrontContentOptions;
use App\Enums\ESortOptions;
use App\Event\User\UserEditedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

readonly class UserSettingsManager
{
    public function __construct(
        private CustomCssService $cssService,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $dispatcher,
        private LoggerInterface $logger,
    )
    {
    }

    public function createDto(User $user): UserSettingsDto
    {
        return new UserSettingsDto(
            $user->notifyOnNewEntry,
            $user->notifyOnNewEntryReply,
            $user->notifyOnNewEntryCommentReply,
            $user->notifyOnNewPost,
            $user->notifyOnNewPostReply,
            $user->notifyOnNewPostCommentReply,
            $user->hideAdult,
            $user->showProfileSubscriptions,
            $user->showProfileFollowings,
            $user->addMentionsEntries,
            $user->addMentionsPosts,
            $user->homepage,
            $user->frontDefaultSort->value,
            $user->commentDefaultSort->value,
            $user->showBoostsOfFollowing,
            $user->featuredMagazines,
            $user->preferredLanguages,
            $user->notifyOnUserSignup,
            $user->directMessageSetting->value,
            $user->frontDefaultContent?->value,
            $user->apDiscoverable,
            $user->apIndexable,
        );
    }

    public function update(User $user, UserSettingsDto $dto): void
    {
        $user->notifyOnNewEntry = $dto->notifyOnNewEntry;
        $user->notifyOnNewPost = $dto->notifyOnNewPost;
        $user->notifyOnNewPostReply = $dto->notifyOnNewPostReply;
        $user->notifyOnNewEntryCommentReply = $dto->notifyOnNewEntryCommentReply;
        $user->notifyOnNewEntryReply = $dto->notifyOnNewEntryReply;
        $user->notifyOnNewPostCommentReply = $dto->notifyOnNewPostCommentReply;
        $user->homepage = $dto->homepage;
        $user->frontDefaultSort = null !== $dto->frontDefaultSort ? ESortOptions::getFromString($dto->frontDefaultSort) : null;
        $user->commentDefaultSort = null !== $dto->commentDefaultSort ? ESortOptions::getFromString($dto->commentDefaultSort) : null;
        $user->showBoostsOfFollowing = $dto->showFollowingBoosts ?? false;
        $user->hideAdult = $dto->hideAdult;
        $user->showProfileSubscriptions = $dto->showProfileSubscriptions;
        $user->showProfileFollowings = $dto->showProfileFollowings;
        $user->addMentionsEntries = $dto->addMentionsEntries;
        $user->addMentionsPosts = $dto->addMentionsPosts;
        $user->featuredMagazines = $dto->featuredMagazines ? array_unique($dto->featuredMagazines) : null;
        $user->preferredLanguages = $dto->preferredLanguages ? array_unique($dto->preferredLanguages) : [];
        $user->directMessageSetting = null !== $dto->directMessageSetting ? EDirectMessageSettings::getFromString($dto->directMessageSetting) : null;
        $user->frontDefaultContent = null !== $dto->frontDefaultContent ? EFrontContentOptions::getFromString($dto->frontDefaultContent) : null;

        if (null !== $dto->notifyOnUserSignup) {
            $user->notifyOnUserSignup = $dto->notifyOnUserSignup;
        }

        if (null !== $dto->discoverable) {
            $user->apDiscoverable = $dto->discoverable;
        }

        if (null !== $dto->indexable) {
            $user->apIndexable = $dto->indexable;
        }

        $this->entityManager->flush();
    }

    public function createStylesDto(User $user): UserStylesDto {
        return new UserStylesDto(
            $user->customCss,
            $user->customPublicContentCss,
            $user->customPublicProfileCss,
            $user->ignoreMagazinesCustomCss,
        );
    }

    public function updateStyles(User $user, UserStylesDto $dto) {
        if($dto->customPublicContentCss === '') {
            $dto->customPublicContentCss = null;
        } else if($dto->customPublicContentCss !== null) {
            $dto->customPublicContentCss = $this->cssService->validatePublicCss($dto->customPublicContentCss, $this->cssService->localUserContentProfile, $user);
        }
        if($dto->customPublicProfileCss === '') {
            $dto->customPublicProfileCss = null;
        } else if($dto->customPublicProfileCss !== null) {
            $dto->customPublicProfileCss = $this->cssService->validatePublicCss($dto->customPublicProfileCss, $this->cssService->localUserPageProfile, null);
        }

        $this->entityManager->beginTransaction();
        $needsFederation = false;
        try {
            $user->customCss = $dto->customCss;
            $user->ignoreMagazinesCustomCss = $dto->ignoreMagazinesCustomCss;

            if($user->customPublicContentCss !== $dto->customPublicContentCss
                || $user->customPublicProfileCss !== $dto->customPublicProfileCss) {
                $user->customPublicContentCss = $dto->customPublicContentCss;
                $user->customPublicProfileCss = $dto->customPublicProfileCss;

                $needsFederation = true;
            }

            // integrity is only for remote user CSS via URL
            if ($user->customPublicContentCssIntegrity !== null || $user->customPublicProfileCssIntegrity !== null) {
                $user->customPublicContentCssIntegrity = null;
                $user->customPublicProfileCssIntegrity = null;

                $this->logger->error('[UserSettingsManager::updateStyles()] user {name} had public css integrity set, which should not be possible for local users', [
                    'name' => $user->getUsername()
                ]);
            }

            $this->entityManager->commit();
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        if($needsFederation) {
            $this->dispatcher->dispatch(new UserEditedEvent($user->getId()));
        }
    }
}
