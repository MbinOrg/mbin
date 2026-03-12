<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\EntryReport;
use App\Entity\Message;
use App\Entity\Post;
use App\Entity\PostComment;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema()]
class ReportResponseDto implements \JsonSerializable
{
    public ?MagazineSmallResponseDto $magazine = null;
    public ?UserSmallResponseDto $reported = null;
    public ?UserSmallResponseDto $reporting = null;
    #[OA\Property(oneOf: [
        new OA\Schema(ref: new Model(type: EntryResponseDto::class)),
        new OA\Schema(ref: new Model(type: EntryCommentResponseDto::class)),
        new OA\Schema(ref: new Model(type: PostResponseDto::class)),
        new OA\Schema(ref: new Model(type: PostCommentResponseDto::class)),
    ])]
    public ?\JsonSerializable $subject = null;
    public ?string $reason = null;
    public ?string $status = null;
    public ?\DateTimeImmutable $createdAt = null;
    public ?\DateTimeImmutable $consideredAt = null;
    public ?UserSmallResponseDto $consideredBy = null;
    public ?int $weight = null;
    public ?int $reportId = null;

    public static function create(
        ?int $id = null,
        ?MagazineSmallResponseDto $magazine = null,
        ?UserSmallResponseDto $reported = null,
        ?UserSmallResponseDto $reporting = null,
        ?string $reason = null,
        ?string $status = null,
        ?int $weight = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $consideredAt = null,
        ?UserSmallResponseDto $consideredBy = null,
    ): self {
        $dto = new ReportResponseDto();
        $dto->reportId = $id;
        $dto->magazine = $magazine;
        $dto->reported = $reported;
        $dto->reporting = $reporting;
        $dto->reason = $reason;
        $dto->status = $status;
        $dto->weight = $weight;
        $dto->createdAt = $createdAt;
        $dto->consideredAt = $consideredAt;
        $dto->consideredBy = $consideredBy;

        return $dto;
    }

    #[OA\Property(
        'type',
        enum: [
            Entry::REPORT_TYPE,
            EntryComment::REPORT_TYPE,
            Post::REPORT_TYPE,
            PostComment::REPORT_TYPE,
            Message::REPORT_TYPE,
            'null_report',
        ]
    )]
    public function getType(): string
    {
        if (null === $this->subject) {
            // item was purged
            return 'null_report';
        }

        switch (\get_class($this->subject)) {
            case EntryResponseDto::class:
                return Entry::REPORT_TYPE;
            case EntryCommentResponseDto::class:
                return EntryComment::REPORT_TYPE;
            case PostResponseDto::class:
                return Post::REPORT_TYPE;
            case PostCommentResponseDto::class:
                return PostComment::REPORT_TYPE;
            case MessageResponseDto::class:
                return Message::REPORT_TYPE;
        }

        throw new \LogicException();
    }

    public function jsonSerialize(): mixed
    {
        $serializedSubject = null;
        if ($this->subject) {
            $visibility = $this->subject->visibility;
            $this->subject->visibility = VisibilityInterface::VISIBILITY_VISIBLE;
            $serializedSubject = $this->subject->jsonSerialize();
            $serializedSubject['visibility'] = $visibility;
        }

        return [
            'reportId' => $this->reportId,
            'type' => $this->getType(),
            'magazine' => $this->magazine->jsonSerialize(),
            'reason' => $this->reason,
            'reported' => $this->reported->jsonSerialize(),
            'reporting' => $this->reporting->jsonSerialize(),
            'subject' => $serializedSubject,
            'status' => $this->status,
            'weight' => $this->weight,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'consideredAt' => $this->consideredAt?->format(\DateTimeInterface::ATOM),
            'consideredBy' => $this->consideredBy?->jsonSerialize(),
        ];
    }
}
