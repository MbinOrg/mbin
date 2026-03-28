<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\ReportDto;
use App\DTO\ReportResponseDto;
use App\Entity\Contracts\HashtagableInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\EntryCommentReport;
use App\Entity\EntryReport;
use App\Entity\Message;
use App\Entity\MessageReport;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\PostCommentReport;
use App\Entity\PostReport;
use App\Entity\Report;
use App\Factory\Contract\ContentDtoFactory;
use App\Repository\TagLinkRepository;
use App\Service\SwitchingServiceRegistry;
use App\Utils\SqlHelpers;
use Doctrine\ORM\EntityManagerInterface;

class ReportFactory
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserFactory $userFactory,
        private readonly MagazineFactory $magazineFactory,
        private readonly TagLinkRepository $tagLinkRepository,
        private readonly SwitchingServiceRegistry $serviceRegistry,
    ) {
    }

    public function createFromDto(ReportDto $dto): Report
    {
        $className = $this->entityManager->getClassMetadata(\get_class($dto->getSubject()))->name.'Report';

        return new $className($dto->getSubject()->getUser(), $dto->getSubject(), $dto->reason);
    }

    public function createResponseDto(Report $report): ReportResponseDto
    {
        $magazine = $report->magazine !== null ? $this->magazineFactory->createSmallDto($report->magazine) : null;
        $toReturn = ReportResponseDto::create(
            $report->getId(),
            $magazine,
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
        $hashtags = $subject instanceof HashtagableInterface ? $this->tagLinkRepository->getTagsOfContent($subject) : [];
        $factory = $this->serviceRegistry->getService($subject, ContentDtoFactory::class);
        $toReturn->subject = $factory->createResponseDto($subject, $hashtags);

        return $toReturn;
    }
}
