<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\HashtagResponseDto;
use App\Entity\Hashtag;
use App\Entity\User;
use App\Repository\TagRepository;
use Symfony\Bundle\SecurityBundle\Security;

class HashtagFactory
{
    public function __construct(
        private readonly Security $security,
        private readonly TagRepository $tagRepository,
    ) {
    }

    public function createDto(Hashtag $tag): HashtagResponseDto
    {
        $counts = $this->tagRepository->getCounts($tag->tag);
        $dto = HashtagResponseDto::create(
            $tag->tag,
            $counts['entry'],
            $counts['entry_comment'],
            $counts['post'],
            $counts['post_comment'],
        );

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if ($currentUser instanceof User) {
            // Only return the user's settings if permission to control settings has been given
            $dto->isBlockedByUser = $this->security->isGranted('ROLE_OAUTH2_HASHTAG:BLOCK') ? $currentUser->isBlockedHashtag($tag) : null;
            $dto->isSubscribedByUser = $this->security->isGranted('ROLE_OAUTH2_HASHTAG:SUBSCRIBE') ? $tag->isSubscribed($currentUser) : null;
        }

        return $dto;
    }
}
