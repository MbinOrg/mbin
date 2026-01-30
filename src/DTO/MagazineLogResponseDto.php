<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Contracts\ContentInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Factory\EntryCommentFactory;
use App\Factory\EntryFactory;
use App\Factory\PostCommentFactory;
use App\Factory\PostFactory;
use App\Repository\TagLinkRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Annotation\Ignore;

#[OA\Schema()]
class MagazineLogResponseDto implements \JsonSerializable
{
    public const LOG_TYPES = [
        'log_entry_deleted',
        'log_entry_restored',
        'log_entry_comment_deleted',
        'log_entry_comment_restored',
        'log_post_deleted',
        'log_post_restored',
        'log_post_comment_deleted',
        'log_post_comment_restored',
        'log_ban',
        'log_unban',
        'log_entry_pinned',
        'log_entry_unpinned',
    ];

    #[OA\Property(enum: self::LOG_TYPES)]
    public ?string $type = null;
    public ?\DateTimeImmutable $createdAt = null;
    public ?MagazineSmallResponseDto $magazine = null;
    public ?UserSmallResponseDto $moderator = null;
    /**
     * The type of the subject is depended on the type of the log entry:
     * - EntryResponseDto when type is 'log_entry_deleted', 'log_entry_restored', 'log_entry_pinned' or 'log_entry_unpinned'
     * - EntryCommentResponseDto when type is 'log_entry_comment_deleted' or 'log_entry_comment_restored'
     * - PostResponseDto when type is 'log_post_deleted' or 'log_post_restored'
     * - PostCommentResponseDto when type is 'log_post_comment_deleted' or 'log_post_comment_restored'
     * - MagazineBanResponseDto when type is 'log_ban' or 'log_unban'
     * - UserSmallResponseDto when type is 'log_moderator_add' or 'log_moderator_remove'
     */
    #[OA\Property('subject')]
    // If this property is named 'subject' the api doc generator will not pick it up.
    // It is still serialized as 'subject', see the jsonSerialize method.
    public EntryResponseDto|EntryCommentResponseDto|PostResponseDto|PostCommentResponseDto|MagazineBanResponseDto|UserSmallResponseDto|null $subject2 = null;

    public static function create(
        MagazineSmallResponseDto $magazine,
        UserSmallResponseDto $moderator,
        \DateTimeImmutable $createdAt,
        string $type,
    ): self {
        $dto = new MagazineLogResponseDto();
        $dto->magazine = $magazine;
        $dto->moderator = $moderator;
        $dto->createdAt = $createdAt;
        $dto->type = $type;

        return $dto;
    }

    public static function createBanUnban(
        MagazineSmallResponseDto $magazine,
        UserSmallResponseDto $moderator,
        \DateTimeImmutable $createdAt,
        string $type,
        MagazineBanResponseDto $banSubject,
    ): self {
        $dto = self::create($magazine, $moderator, $createdAt, $type);
        $dto->subject2 = $banSubject;

        return $dto;
    }

    public static function createModeratorAddRemove(
        MagazineSmallResponseDto $magazine,
        UserSmallResponseDto $moderator,
        \DateTimeImmutable $createdAt,
        string $type,
        UserSmallResponseDto $moderatorSubject,
    ): self {
        $dto = self::create($magazine, $moderator, $createdAt, $type);
        $dto->subject2 = $moderatorSubject;

        return $dto;
    }

    #[Ignore]
    public function setSubject(
        ?ContentInterface $subject,
        EntryFactory $entryFactory,
        EntryCommentFactory $entryCommentFactory,
        PostFactory $postFactory,
        PostCommentFactory $postCommentFactory,
        TagLinkRepository $tagLinkRepository,
    ): void {
        switch ($this->type) {
            case 'log_entry_deleted':
            case 'log_entry_restored':
            case 'log_entry_pinned':
            case 'log_entry_unpinned':
                \assert($subject instanceof Entry);
                $this->subject2 = $entryFactory->createResponseDto($subject, tags: $tagLinkRepository->getTagsOfContent($subject));
                break;
            case 'log_entry_comment_deleted':
            case 'log_entry_comment_restored':
                \assert($subject instanceof EntryComment);
                $this->subject2 = $entryCommentFactory->createResponseDto($subject, tags: $tagLinkRepository->getTagsOfContent($subject));
                break;
            case 'log_post_deleted':
            case 'log_post_restored':
                \assert($subject instanceof Post);
                $this->subject2 = $postFactory->createResponseDto($subject, tags: $tagLinkRepository->getTagsOfContent($subject));
                break;
            case 'log_post_comment_deleted':
            case 'log_post_comment_restored':
                \assert($subject instanceof PostComment);
                $this->subject2 = $postCommentFactory->createResponseDto($subject, tags: $tagLinkRepository->getTagsOfContent($subject));
                break;
            default:
                break;
        }
    }

    public function jsonSerialize(): mixed
    {
        return [
            'type' => $this->type,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'magazine' => $this->magazine,
            'moderator' => $this->moderator,
            'subject' => $this->subject2?->jsonSerialize(),
        ];
    }
}
