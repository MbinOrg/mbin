<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Moderator;
use App\Entity\Report;
use App\Entity\ReportApprovedNotification;
use App\Entity\ReportCreatedNotification;
use App\Event\NotificationCreatedEvent;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ReportNotificationManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function sendReportCreatedNotification(Report $report): void
    {
        $receivers = [];
        foreach ($report->magazine->moderators as /* @var Moderator $moderator */ $moderator) {
            if (null === $moderator->user->apId) {
                $receivers[] = $moderator->user;
            }
        }

        foreach ($this->userRepository->findAllModerators() as $moderator) {
            if (null === $moderator->apId) {
                $receivers[] = $moderator;
            }
        }

        foreach ($this->userRepository->findAllAdmins() as $admin) {
            if (null === $admin->apId) {
                $receivers[] = $admin;
            }
        }

        $map = [];
        foreach ($receivers as $receiver) {
            if (!\array_key_exists($receiver->getId(), $map)) {
                $map[$receiver->getId()] = true;
                $n = new ReportCreatedNotification($receiver, $report);
                $this->entityManager->persist($n);
                $this->dispatcher->dispatch(new NotificationCreatedEvent($n));
            }
        }

        $this->entityManager->flush();
    }

    public function sendReportRejectedNotification(Report $report): void
    {
    }

    public function sendReportApprovedNotification(Report $report): void
    {
        if (null === $report->reported->apId) {
            $notification = new ReportApprovedNotification($report->reported, $report);
            $this->entityManager->persist($notification);
            $this->entityManager->flush();
            $this->dispatcher->dispatch(new NotificationCreatedEvent($notification));
        }
    }
}
