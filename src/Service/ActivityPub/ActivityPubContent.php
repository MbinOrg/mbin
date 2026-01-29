<?php

declare(strict_types=1);

namespace App\Service\ActivityPub;

use App\DTO\EntryCommentDto;
use App\DTO\EntryDto;
use App\DTO\PostCommentDto;
use App\DTO\PostDto;
use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Contracts\VisibilityInterface;
use App\Entity\User;
use App\Utils\JsonldUtils;

abstract class ActivityPubContent
{
    /**
     * @throws \LogicException
     */
    protected function getVisibility(array $object, User $actor): string
    {
        $toAndCC = array_merge(JsonldUtils::getArrayValue($object, 'to'), JsonldUtils::getArrayValue($object, 'cc'));
        if (!\in_array(ActivityPubActivityInterface::PUBLIC_URL, $toAndCC)) {
            if (!\in_array($actor->apFollowersUrl, $toAndCC)) {
                throw new \LogicException('PM: not implemented.');
            }

            return VisibilityInterface::VISIBILITY_PRIVATE;
        }

        return VisibilityInterface::VISIBILITY_VISIBLE;
    }

    protected function handleDate(PostDto|PostCommentDto|EntryCommentDto|EntryDto $dto, string $date): void
    {
        $dto->createdAt = new \DateTimeImmutable($date);
        $dto->lastActive = new \DateTime($date);
    }

    protected function handleSensitiveMedia(PostDto|PostCommentDto|EntryCommentDto|EntryDto $dto, string|bool $sensitive): void
    {
        if (true === filter_var($sensitive, FILTER_VALIDATE_BOOLEAN)) {
            $dto->isAdult = true;
        }
    }
}
