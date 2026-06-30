<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\ReportDto;
use App\Entity\Contracts\ContentInterface;
use App\Entity\Contracts\ContentVisibilityInterface;
use App\Entity\Report;
use App\Entity\User;
use App\Event\Report\ReportApprovedEvent;
use App\Event\Report\ReportRejectedEvent;
use App\Event\Report\SubjectReportedEvent;
use App\Exception\SubjectHasBeenReportedException;
use App\Factory\ReportFactory;
use App\Repository\ReportRepository;
use App\Service\Contracts\ContentManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class ReportManager
{
    public function __construct(
        private readonly ReportFactory $factory,
        private readonly ReportRepository $repository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly EntityManagerInterface $entityManager,
        private readonly SwitchingServiceRegistry $serviceRegistry,
    ) {
    }

    public function report(ReportDto $dto, User $reporting): Report
    {
        $report = $this->repository->findBySubject($dto->getSubject());

        if ($report) {
            $report->increaseWeight();
            $this->entityManager->flush();
            throw new SubjectHasBeenReportedException();
        }

        $report = $this->factory->createFromDto($dto);
        $report->reporting = $reporting;

        $this->entityManager->persist($report);
        $this->entityManager->flush();

        $this->dispatcher->dispatch(new SubjectReportedEvent($report));

        return $report;
    }

    public function reject(Report $report, User $moderator): void
    {
        $report->status = Report::STATUS_REJECTED;
        $report->consideredBy = $moderator;
        $report->consideredAt = new \DateTimeImmutable();

        $subject = $report->getSubject();
        if ($subject instanceof ContentVisibilityInterface && $subject->isTrashed()) {
            $manager = $this->serviceRegistry->getService($subject, ContentManagerInterface::class);
            $manager->restore($moderator, $subject);
        }

        $this->entityManager->flush();

        $this->dispatcher->dispatch(new ReportRejectedEvent($report));
    }

    public function accept(Report $report, User $moderator): void
    {
        $report->status = Report::STATUS_APPROVED;
        $report->consideredBy = $moderator;
        $report->consideredAt = new \DateTimeImmutable();

        $subject = $report->getSubject();
        if($subject instanceof ContentInterface) {
            $manager = $this->serviceRegistry->getService($subject, ContentManagerInterface::class);
            $manager->delete($moderator, $subject);
        }

        $this->entityManager->flush();

        $this->dispatcher->dispatch(new ReportApprovedEvent($report));
    }
}
