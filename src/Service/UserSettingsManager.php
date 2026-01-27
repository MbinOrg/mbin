<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\UserSettingsDto;
use App\Entity\User;
use App\Enums\EDirectMessageSettings;
use App\Enums\EFrontContentOptions;
use App\Enums\ESortOptions;
use Doctrine\ORM\EntityManagerInterface;

class UserSettingsManager
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
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
            $user->featuredMagazines,
            $user->preferredLanguages,
            $user->customCss,
            $user->ignoreMagazinesCustomCss,
            $user->notifyOnUserSignup,
            $user->directMessageSetting->value,
            $user->frontDefaultContent->value,
            $user->apDiscoverable,
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
        $user->frontDefaultSort = null != $dto->frontDefaultSort ? ESortOptions::getFromString($dto->frontDefaultSort) : null;
        $user->commentDefaultSort = null != $dto->commentDefaultSort ? ESortOptions::getFromString($dto->commentDefaultSort) : null;
        $user->hideAdult = $dto->hideAdult;
        $user->showProfileSubscriptions = $dto->showProfileSubscriptions;
        $user->showProfileFollowings = $dto->showProfileFollowings;
        $user->addMentionsEntries = $dto->addMentionsEntries;
        $user->addMentionsPosts = $dto->addMentionsPosts;
        $user->featuredMagazines = $dto->featuredMagazines ? array_unique($dto->featuredMagazines) : null;
        $user->preferredLanguages = $dto->preferredLanguages ? array_unique($dto->preferredLanguages) : [];
        $user->customCss = $dto->customCss;
        $user->ignoreMagazinesCustomCss = $dto->ignoreMagazinesCustomCss;
        $user->directMessageSetting = null != $dto->directMessageSetting ? EDirectMessageSettings::getFromString($dto->directMessageSetting) : null;
        $user->frontDefaultContent = null != $dto->frontDefaultContent ? EFrontContentOptions::getFromString($dto->frontDefaultContent) : null;

        if (null !== $dto->notifyOnUserSignup) {
            $user->notifyOnUserSignup = $dto->notifyOnUserSignup;
        }

        if (null !== $dto->discoverable) {
            $user->apDiscoverable = $dto->discoverable;
        }

        $this->entityManager->flush();
    }
}
