<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\ReportDto;
use App\DTO\ReportResponseDto;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\EntryCommentReport;
use App\Entity\EntryReport;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\PostCommentReport;
use App\Entity\PostReport;
use App\Entity\Report;
use App\Repository\TagLinkRepository;
use Doctrine\ORM\EntityManagerInterface;

class ReportFactory
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserFactory $userFactory,
        private readonly MagazineFactory $magazineFactory,
        private readonly EntryFactory $entryFactory,
        private readonly PostFactory $postFactory,
        private readonly EntryCommentFactory $entryCommentFactory,
        private readonly PostCommentFactory $postCommentFactory,
        private readonly TagLinkRepository $tagLinkRepository,
    ) {
    }

    public function createFromDto(ReportDto $dto): Report
    {
        $className = $this->entityManager->getClassMetadata(\get_class($dto->getSubject()))->name.'Report';

        return new $className($dto->getSubject()->user, $dto->getSubject(), $dto->reason);
    }

    public function createResponseDto(Report $report): ReportResponseDto
    {
        $toReturn = ReportResponseDto::create(
            $report->getId(),
            $this->magazineFactory->createSmallDto($report->magazine),
            $this->userFactory->createSmallDto($report->reported),
            $this->userFactory->createSmallDto($report->reporting),
            $report->reason,
            $report->status,
            $report->weight,
            $report->createdAt,
            $report->consideredAt,
            $report->consideredBy ? $this->userFactory->createSmallDto($report->consideredBy) : null
        );

        $subject = $report->getSubject();
        switch (\get_class($report)) {
            case EntryReport::class:
                \assert($subject instanceof Entry);
                $toReturn->subject = $this->entryFactory->createResponseDto($subject, tags: $this->tagLinkRepository->getTagsOfEntry($subject));
                break;
            case EntryCommentReport::class:
                \assert($subject instanceof EntryComment);
                $toReturn->subject = $this->entryCommentFactory->createResponseDto($subject, tags: $this->tagLinkRepository->getTagsOfEntryComment($subject));
                break;
            case PostReport::class:
                \assert($subject instanceof Post);
                $toReturn->subject = $this->postFactory->createResponseDto($subject, tags: $this->tagLinkRepository->getTagsOfPost($subject));
                break;
            case PostCommentReport::class:
                \assert($subject instanceof PostComment);
                $toReturn->subject = $this->postCommentFactory->createResponseDto($subject, tags: $this->tagLinkRepository->getTagsOfPostComment($subject));
                break;
            default:
                throw new \LogicException();
        }

        return $toReturn;
    }
}
