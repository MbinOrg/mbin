<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\EntryCommentDto;
use App\DTO\EntryCommentResponseDto;
use App\Entity\EntryComment;
use App\Entity\User;
use App\PageView\EntryCommentPageView;
use App\Repository\BookmarkListRepository;
use App\Repository\TagLinkRepository;
use Symfony\Bundle\SecurityBundle\Security;

class EntryCommentFactory
{
    public function __construct(
        private readonly Security $security,
        private readonly ImageFactory $imageFactory,
        private readonly UserFactory $userFactory,
        private readonly MagazineFactory $magazineFactory,
        private readonly TagLinkRepository $tagLinkRepository,
        private readonly BookmarkListRepository $bookmarkListRepository,
    ) {
    }

    public function createFromDto(EntryCommentDto $dto, User $user): EntryComment
    {
        return new EntryComment(
            $dto->body,
            $dto->entry,
            $user,
            $dto->parent,
            $dto->ip
        );
    }

    public function createResponseDto(EntryCommentDto|EntryComment $comment, array $tags, int $childCount = 0): EntryCommentResponseDto
    {
        $dto = $comment instanceof EntryComment ? $this->createDto($comment) : $comment;

        return EntryCommentResponseDto::create(
            $dto->getId(),
            $this->userFactory->createSmallDto($dto->user),
            $this->magazineFactory->createSmallDto($dto->magazine),
            $dto->entry->getId(),
            $dto->parent?->getId(),
            $dto->parent?->root?->getId() ?? $dto->parent?->getId(),
            $dto->image,
            $dto->body,
            $dto->lang,
            $dto->isAdult,
            $dto->uv,
            $dto->dv,
            $dto->favouriteCount,
            $dto->visibility,
            $dto->apId,
            $dto->mentions,
            $tags,
            $dto->createdAt,
            $dto->editedAt,
            $dto->lastActive,
            $childCount,
            bookmarks: $this->bookmarkListRepository->getBookmarksOfContentInterface($comment),
        );
    }

    public function createResponseTree(EntryComment $comment, EntryCommentPageView $commentPageView, int $depth = -1, ?bool $canModerate = null): EntryCommentResponseDto
    {
        $commentDto = $this->createDto($comment);
        $toReturn = $this->createResponseDto($commentDto, $this->tagLinkRepository->getTagsOfEntryComment($comment), array_reduce($comment->children->toArray(), EntryCommentResponseDto::class.'::recursiveChildCount', 0));
        $toReturn->isFavourited = $commentDto->isFavourited;
        $toReturn->userVote = $commentDto->userVote;
        $toReturn->canAuthUserModerate = $canModerate;

        if (0 === $depth) {
            return $toReturn;
        }

        foreach ($comment->getChildrenByCriteria($commentPageView) as $childComment) {
            \assert($childComment instanceof EntryComment);
            if (($user = $this->security->getUser()) && $user instanceof User) {
                if ($user->isBlocked($childComment->user)) {
                    continue;
                }
            }
            $child = $this->createResponseTree($childComment, $commentPageView, $depth > 0 ? $depth - 1 : -1, $canModerate);
            $toReturn->children[] = $child;
        }

        return $toReturn;
    }

    public function createDto(EntryComment $comment): EntryCommentDto
    {
        $dto = new EntryCommentDto();
        $dto->magazine = $comment->magazine;
        $dto->entry = $comment->entry;
        $dto->user = $comment->user;
        $dto->body = $comment->body;
        $dto->lang = $comment->lang;
        $dto->parent = $comment->parent;
        $dto->isAdult = $comment->isAdult;
        $dto->image = $comment->image ? $this->imageFactory->createDto($comment->image) : null;
        $dto->visibility = $comment->visibility;
        $dto->uv = $comment->countUpVotes();
        $dto->dv = $comment->countDownVotes();
        $dto->favouriteCount = $comment->favouriteCount;
        $dto->mentions = $comment->mentions;
        $dto->createdAt = $comment->createdAt;
        $dto->editedAt = $comment->editedAt;
        $dto->lastActive = $comment->lastActive;
        $dto->apId = $comment->apId;
        $dto->apLikeCount = $comment->apLikeCount;
        $dto->apDislikeCount = $comment->apDislikeCount;
        $dto->apShareCount = $comment->apShareCount;
        $dto->setId($comment->getId());

        $currentUser = $this->security->getUser();
        // Only return the user's vote if permission to control voting has been given
        $dto->isFavourited = $this->security->isGranted('ROLE_OAUTH2_ENTRY_COMMENT:VOTE') ? $comment->isFavored($currentUser) : null;
        $dto->userVote = $this->security->isGranted('ROLE_OAUTH2_ENTRY_COMMENT:VOTE') ? $comment->getUserChoice($currentUser) : null;

        return $dto;
    }
}
