<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Contracts\ContentInterface;
use App\Entity\EntryComment;
use App\Entity\EntryCommentCreatedNotification;
use App\Entity\EntryCommentDeletedNotification;
use App\Entity\EntryCommentEditedNotification;
use App\Entity\EntryCommentMentionedNotification;
use App\Entity\EntryCommentReplyNotification;
use App\Entity\Notification;
use App\Event\NotificationCreatedEvent;
use App\Factory\MagazineFactory;
use App\Factory\UserFactory;
use App\Repository\MagazineLogRepository;
use App\Repository\MagazineSubscriptionRepository;
use App\Repository\NotificationRepository;
use App\Repository\NotificationSettingsRepository;
use App\Repository\UserRepository;
use App\Service\Contracts\ContentNotificationManagerInterface;
use App\Service\GenerateHtmlClassService;
use App\Service\ImageManager;
use App\Service\MentionManager;
use App\Service\SettingsManager;
use App\Utils\IriGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class EntryCommentNotificationManager implements ContentNotificationManagerInterface
{
    use NotificationTrait;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly MentionManager $mentionManager,
        private readonly NotificationRepository $notificationRepository,
        private readonly MagazineLogRepository $magazineLogRepository,
        private readonly MagazineSubscriptionRepository $magazineRepository,
        private readonly MagazineFactory $magazineFactory,
        private readonly UserFactory $userFactory,
        private readonly HubInterface $publisher,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageManager $imageManager,
        private readonly GenerateHtmlClassService $classService,
        private readonly SettingsManager $settingsManager,
        private readonly NotificationSettingsRepository $notificationSettingsRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function sendCreated(ContentInterface $subject): void
    {
        if ($subject->user->isBanned || $subject->user->isDeleted || $subject->user->isTrashed() || $subject->user->isSoftDeleted()) {
            return;
        }
        if (!$subject instanceof EntryComment) {
            throw new \LogicException();
        }
        $comment = $subject;

        $mentioned = $this->sendMentionedNotification($comment);

        $this->notifyMagazine(new EntryCommentCreatedNotification($comment->user, $comment));

        $userIdsToNotify = $this->notificationSettingsRepository->findNotificationSubscribersByTarget($comment);
        $usersToNotify = $this->userRepository->findBy(['id' => $userIdsToNotify]);

        if (\count($mentioned)) {
            $usersToNotify = array_filter($usersToNotify, fn ($user) => !\in_array($user, $mentioned));
        }

        foreach ($usersToNotify as $subscriber) {
            if (null !== $comment->parent && $comment->parent->isAuthor($subscriber)) {
                $notification = new EntryCommentReplyNotification($subscriber, $comment);
            } else {
                $notification = new EntryCommentCreatedNotification($subscriber, $comment);
            }
            $this->entityManager->persist($notification);
            $this->eventDispatcher->dispatch(new NotificationCreatedEvent($notification));
        }

        $this->entityManager->flush();
    }

    private function sendMentionedNotification(EntryComment $subject): array
    {
        $users = [];
        $mentions =  $this->mentionManager->clearLocal($this->mentionManager->extract($subject->body));

        foreach ($this->mentionManager->getUsersFromArray($mentions) as $user) {
            if (!$user->apId and !$user->isBlocked($subject->getUser())) {
                $notification = new EntryCommentMentionedNotification($user, $subject);
                $this->entityManager->persist($notification);
                $this->eventDispatcher->dispatch(new NotificationCreatedEvent($notification));
            }

            $users[] = $user;
        }

        return $users;
    }

    private function notifyUser(EntryCommentReplyNotification $notification): void
    {
        if (false === $this->settingsManager->get('KBIN_MERCURE_ENABLED')) {
            return;
        }

        try {
            $iri = IriGenerator::getIriFromResource($notification->user);

            $update = new Update(
                $iri,
                $this->getResponse($notification)
            );

            $this->publisher->publish($update);
        } catch (\Exception $e) {
        }
    }

    private function getResponse(Notification $notification): string
    {
        $class = explode('\\', $this->entityManager->getClassMetadata(\get_class($notification))->name);

        /**
         * @var EntryComment $comment ;
         */
        $comment = $notification->getComment();

        return json_encode(
            [
                'op' => end($class),
                'id' => $comment->getId(),
                'htmlId' => $this->classService->fromEntity($comment),
                'parent' => $comment->parent ? [
                    'id' => $comment->parent->getId(),
                    'htmlId' => $this->classService->fromEntity($comment->parent),
                ] : null,
                'parentSubject' => [
                    'id' => $comment->entry->getId(),
                    'htmlId' => $this->classService->fromEntity($comment->entry),
                ],
                'title' => $comment->entry->title,
                'body' => $comment->body,
                'icon' => $this->imageManager->getUrl($comment->image),
                //                'image' => $this->imageManager->getUrl($comment->image),
                'url' => $this->urlGenerator->generate('entry_comment_view', [
                    'magazine_name' => $comment->magazine->name,
                    'entry_id' => $comment->entry->getId(),
                    'slug' => $comment->entry->slug,
                    'comment_id' => $comment->getId(),
                ]).'#entry-comment-'.$comment->getId(),
                //                'toast' => $this->twig->render('_layout/_toast.html.twig', ['notification' => $notification]),
            ]
        );
    }

    private function notifyMagazine(Notification $notification): void
    {
        if (false === $this->settingsManager->get('KBIN_MERCURE_ENABLED')) {
            return;
        }

        try {
            $iri = IriGenerator::getIriFromResource($notification->getComment()->magazine);

            $update = new Update(
                ['pub', $iri],
                $this->getResponse($notification)
            );

            $this->publisher->publish($update);
        } catch (\Exception $e) {
        }
    }

    public function sendEdited(ContentInterface $subject): void
    {
        if (!$subject instanceof EntryComment) {
            throw new \LogicException();
        }
        $this->notifyMagazine(new EntryCommentEditedNotification($subject->user, $subject));
    }

    public function sendDeleted(ContentInterface $subject): void
    {
        if (!$subject instanceof EntryComment) {
            throw new \LogicException();
        }
        $this->notifyMagazine($notification = new EntryCommentDeletedNotification($subject->user, $subject));
    }

    public function purgeNotifications(EntryComment $comment): void
    {
        $this->notificationRepository->removeEntryCommentNotifications($comment);
    }

    public function purgeMagazineLog(EntryComment $comment): void
    {
        $this->magazineLogRepository->removeEntryCommentLogs($comment);
    }
}
