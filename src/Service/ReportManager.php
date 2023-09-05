<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\ReportDto;
use App\Entity\Report;
use App\Entity\User;
use App\Event\Report\ReportRejectedEvent;
use App\Event\Report\SubjectReportedEvent;
use App\Exception\SubjectHasBeenReportedException;
use App\Factory\ReportFactory;
use App\Repository\ReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class ReportManager
{
    public function __construct(
        private readonly ReportFactory $factory,
        private readonly ReportRepository $repository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function report(ReportDto $dto, User $reporting): Report
    {
        $existed = $report = $this->repository->findBySubject($dto->getSubject());

        if ($report) {
            if ($report->reporting === $reporting) {
                $report->increaseWeight();
                throw new SubjectHasBeenReportedException();
            }
        }

        if (!$report || Report::STATUS_PENDING === $report->status) {
            $report = $this->factory->createFromDto($dto);
            $report->reporting = $reporting;
        }

        $this->entityManager->persist($report);
        $this->entityManager->flush();

        if (!$existed) {
            $this->dispatcher->dispatch(new SubjectReportedEvent($report));
        }

        return $report;
    }

    public function reject(Report $report, User $moderator)
    {
        $report->status = Report::STATUS_REJECTED;
        $report->consideredBy = $moderator;
        $report->consideredAt = new \DateTimeImmutable();

        $this->entityManager->flush();

        $this->dispatcher->dispatch(new ReportRejectedEvent($report));
    }
}
