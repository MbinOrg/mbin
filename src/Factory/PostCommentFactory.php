<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\PostCommentDto;
use App\DTO\PostCommentResponseDto;
use App\Entity\PostComment;
use App\Entity\User;
use App\PageView\PostCommentPageView;
use App\Repository\BookmarkListRepository;
use App\Repository\PostRepository;
use App\Repository\TagLinkRepository;
use Symfony\Bundle\SecurityBundle\Security;

class PostCommentFactory
{
    public function __construct(
        private readonly Security $security,
        private readonly UserFactory $userFactory,
        private readonly MagazineFactory $magazineFactory,
        private readonly ImageFactory $imageFactory,
        private readonly PostRepository $postRepository,
        private readonly TagLinkRepository $tagLinkRepository,
        private readonly BookmarkListRepository $bookmarkListRepository,
    ) {
    }

    public function createFromDto(PostCommentDto $dto, User $user): PostComment
    {
        return new PostComment(
            $dto->body,
            $dto->post,
            $user,
            $dto->parent,
            $dto->ip
        );
    }

    public function createResponseDto(PostCommentDto|PostComment $comment, array $tags, int $childCount = 0): PostCommentResponseDto
    {
        $dto = $comment instanceof PostComment ? $this->createDto($comment) : $comment;

        return PostCommentResponseDto::create(
            $dto->getId(),
            $this->userFactory->createSmallDto($dto->user),
            $this->magazineFactory->createSmallDto($dto->magazine),
            $this->postRepository->find($dto->post->getId()),
            $dto->parent,
            $childCount,
            $dto->image,
            $dto->body,
            $dto->lang,
            $dto->isAdult,
            $dto->uv,
            $dto->dv,
            $dto->favourites,
            $dto->visibility,
            $dto->apId,
            $dto->mentions,
            $tags,
            $dto->createdAt,
            $dto->editedAt,
            $dto->lastActive,
            bookmarks: $this->bookmarkListRepository->getBookmarksOfContentInterface($comment),
            isAuthorModeratorInMagazine: $dto->magazine->userIsModerator($dto->user),
        );
    }

    public function createResponseTree(PostComment $comment, PostCommentPageView $criteria, int $depth, ?bool $canModerate = null): PostCommentResponseDto
    {
        $commentDto = $this->createDto($comment);
        $toReturn = $this->createResponseDto($commentDto, $this->tagLinkRepository->getTagsOfPostComment($comment), array_reduce($comment->children->toArray(), PostCommentResponseDto::class.'::recursiveChildCount', 0));
        $toReturn->isFavourited = $commentDto->isFavourited;
        $toReturn->userVote = $commentDto->userVote;
        $toReturn->canAuthUserModerate = $canModerate;

        if (0 === $depth) {
            return $toReturn;
        }

        foreach ($comment->getChildrenByCriteria($criteria) as /** @var PostComment $childComment */ $childComment) {
            \assert($childComment instanceof PostComment);
            if (($user = $this->security->getUser()) && $user instanceof User) {
                if ($user->isBlocked($childComment->user)) {
                    continue;
                }
            }
            $child = $this->createResponseTree($childComment, $criteria, $depth > 0 ? $depth - 1 : -1, $canModerate);
            $toReturn->children[] = $child;
        }

        return $toReturn;
    }

    public function createDto(PostComment $comment): PostCommentDto
    {
        $dto = new PostCommentDto();
        $dto->magazine = $comment->magazine;
        $dto->post = $comment->post;
        $dto->user = $comment->user;
        $dto->body = $comment->body;
        $dto->lang = $comment->lang;
        $dto->image = $comment->image ? $this->imageFactory->createDto($comment->image) : null;
        $dto->isAdult = $comment->isAdult;
        $dto->uv = $comment->countUpVotes();
        $dto->dv = $comment->countDownVotes();
        $dto->favourites = $comment->favouriteCount;
        $dto->visibility = $comment->visibility;
        $dto->createdAt = $comment->createdAt;
        $dto->editedAt = $comment->editedAt;
        $dto->lastActive = $comment->lastActive;
        $dto->setId($comment->getId());
        $dto->parent = $comment->parent;
        $dto->mentions = $comment->mentions;
        $dto->apId = $comment->apId;
        $dto->apLikeCount = $comment->apLikeCount;
        $dto->apDislikeCount = $comment->apDislikeCount;
        $dto->apShareCount = $comment->apShareCount;

        $currentUser = $this->security->getUser();
        // Only return the user's vote if permission to control voting has been given
        $dto->isFavourited = $this->security->isGranted('ROLE_OAUTH2_POST_COMMENT:VOTE') ? $comment->isFavored($currentUser) : null;
        $dto->userVote = $this->security->isGranted('ROLE_OAUTH2_POST_COMMENT:VOTE') ? $comment->getUserChoice($currentUser) : null;

        return $dto;
    }
}
