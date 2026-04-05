<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Poll;
use App\Entity\PollEditedNotification;
use App\Entity\PollEndedNotification;
use App\Repository\PollRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class PollNotificationManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PollRepository $pollRepository,
    ) {
    }

    public function sendPollEditedNotification(Poll $poll): void
    {
        foreach ($this->pollRepository->getAllLocalVotersOfPoll($poll) as $user) {
            $notification = new PollEditedNotification($user, $poll);
            $this->entityManager->persist($notification);
        }
        $this->entityManager->flush();
    }

    public function sendPollEndedNotification(Poll $poll): void
    {
        foreach ($this->pollRepository->getAllLocalVotersOfPoll($poll) as $user) {
            $notification = new PollEndedNotification($user, $poll);
            $this->entityManager->persist($notification);
        }
        $this->entityManager->flush();
    }
}
