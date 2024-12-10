<?php

declare(strict_types=1);

namespace App\Repository;

use App\DTO\EntryDto;
use App\DTO\MagazineDto;
use App\DTO\PostDto;
use App\DTO\UserDto;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Entity\NotificationSettings;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Enums\ENotificationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * @method NotificationSettings|null find($id, $lockMode = null, $lockVersion = null)
 * @method NotificationSettings|null findOneBy(array $criteria, array $orderBy = null)
 * @method NotificationSettings[]    findAll()
 * @method NotificationSettings[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationSettingsRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($registry, NotificationSettings::class);
    }

    public function findOneByTarget(User $user, Entry|EntryDto|Post|PostDto|User|UserDto|Magazine|MagazineDto $target): ?NotificationSettings
    {
        $qb = $this->createQueryBuilder('ns')
            ->where('ns.user = :user');

        if ($target instanceof User || $target instanceof UserDto) {
            $qb->andWhere('ns.targetUser = :target');
        } elseif ($target instanceof Magazine || $target instanceof MagazineDto) {
            $qb->andWhere('ns.magazine = :target');
        } elseif ($target instanceof Entry || $target instanceof EntryDto) {
            $qb->andWhere('ns.entry = :target');
        } elseif ($target instanceof Post || $target instanceof PostDto) {
            $qb->andWhere('ns.post = :target');
        }
        $qb->setParameter('target', $target->getId());
        $qb->setParameter('user', $user);

        return $qb->getQuery()
            ->getOneOrNullResult();
    }

    public function setStatusByTarget(User $user, Entry|Post|User|Magazine $target, ENotificationStatus $status): void
    {
        $setting = $this->findOneByTarget($user, $target);
        if (null === $setting) {
            $setting = new NotificationSettings($user, $target, $status);
        } else {
            $setting->setStatus($status);
        }
        $this->entityManager->persist($setting);
        $this->entityManager->flush();
    }

    /**
     * gets the users that should be notified about the created of $target. This respects user and magazine blocks
     * as well as custom notification settings and the users default notification settings.
     *
     * @return int[]
     *
     * @throws Exception
     */
    public function findNotificationSubscribersByTarget(Entry|EntryComment|Post|PostComment $target): array
    {
        if ($target instanceof Entry || $target instanceof EntryComment) {
            $targetCol = 'entry_id';
            if ($target instanceof Entry) {
                $targetId = $target->getId();
                $notifyCol = 'notify_on_new_entry';
                $isMagazineLevel = true;
                $dontNeedSubscription = false;
                $dontNeedToBeAuthor = true;
                $targetParentUserId = null;
            } else {
                $targetId = $target->entry->getId();
                if (null === $target->parent) {
                    $notifyCol = 'notify_on_new_entry_reply';
                    $targetParentUserId = $target->entry->user->getId();
                } else {
                    $notifyCol = 'notify_on_new_entry_comment_reply';
                    $targetParentUserId = $target->parent->user->getId();
                }
                $isMagazineLevel = false;
                $dontNeedSubscription = true;
                $dontNeedToBeAuthor = false;
            }
        } else {
            $targetCol = 'post_id';
            if ($target instanceof Post) {
                $targetId = $target->getId();
                $notifyCol = 'notify_on_new_post';
                $isMagazineLevel = true;
                $dontNeedSubscription = false;
                $dontNeedToBeAuthor = true;
                $targetParentUserId = null;
            } else {
                $targetId = $target->post->getId();
                if (null === $target->parent) {
                    $notifyCol = 'notify_on_new_post_reply';
                    $targetParentUserId = $target->post->user->getId();
                } else {
                    $notifyCol = 'notify_on_new_post_comment_reply';
                    $targetParentUserId = $target->parent->user->getId();
                }
                $isMagazineLevel = false;
                $dontNeedSubscription = true;
                $dontNeedToBeAuthor = false;
            }
        }

        $isMagazineLevelString = $isMagazineLevel ? 'true' : 'false';
        $isNotMagazineLevelString = !$isMagazineLevel ? 'true' : 'false';
        $dontNeedSubscriptionString = $dontNeedSubscription ? 'true' : 'false';
        $dontNeedToBeAuthorString = $dontNeedToBeAuthor ? 'true' : 'false';

        $sql = "SELECT u.id FROM \"user\" u
            LEFT JOIN notification_settings ns_user ON ns_user.user_id = u.id AND ns_user.target_user_id = :targetUserId
            LEFT JOIN notification_settings ns_post ON ns_post.user_id = u.id AND ns_post.$targetCol = :targetId
            LEFT JOIN notification_settings ns_mag ON ns_mag.user_id = u.id AND ns_mag.magazine_id = :magId
            WHERE
                u.ap_id IS NULL
                AND u.id <> :targetUserId
                AND (
                    COALESCE(ns_user.notification_status, :normal) = :loud
                    OR (
                        COALESCE(ns_user.notification_status, :normal) = :normal
                        AND COALESCE(ns_post.notification_status, :normal) = :loud
                    )
                    OR (
                        COALESCE(ns_user.notification_status, :normal) = :normal
                        AND COALESCE(ns_post.notification_status, :normal) = :normal
                        AND COALESCE(ns_mag.notification_status, :normal) = :loud
                        -- deactivate loud magazine notifications for comments
                        AND $isMagazineLevelString
                    )
                    OR (
                        COALESCE(ns_user.notification_status, :normal) = :normal
                        AND COALESCE(ns_post.notification_status, :normal) = :normal
                        AND (
                            -- ignore the magazine level settings for comments
                            COALESCE(ns_mag.notification_status, :normal) = :normal
                            OR $isNotMagazineLevelString
                        )
                        AND u.$notifyCol = true
                        AND (
                            -- deactivate magazine subscription need for comments
                            $dontNeedSubscriptionString
                            OR EXISTS (SELECT * FROM magazine_subscription ms WHERE ms.user_id = u.id AND ms.magazine_id = :magId)
                        )
                        AND (
                            -- deactivate the need to be the author of the parent to receive notifications
                            $dontNeedToBeAuthorString
                            OR u.id = :targetParentUserId
                        )
                    )
                )
                AND NOT EXISTS (SELECT * FROM user_block ub WHERE ub.blocker_id = u.id AND ub.blocked_id = :targetUserId)
        ";
        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'normal' => ENotificationStatus::Default->value,
            'loud' => ENotificationStatus::Loud->value,
            'targetUserId' => $target->user->getId(),
            'targetId' => $targetId,
            'magId' => $target->magazine->getId(),
            'targetParentUserId' => $targetParentUserId,
        ]);
        $rows = $result->fetchAllAssociative();
        $this->logger->debug('got subscribers for target {c} id {id}: {subs}, (magLevel: {ml}, notMagLevel: {nml}, targetCol: {tc}, notifyCol: {nc}, dontNeedSubs: {dns}, doneNeedAuthor: {dna})', [
            'c' => \get_class($target),
            'id' => $target->getId(),
            'subs' => $rows,
            'ml' => $isMagazineLevelString,
            'nml' => $isNotMagazineLevelString,
            'tc' => $targetCol,
            'nc' => $notifyCol,
            'dns' => $dontNeedSubscriptionString,
            'dna' => $dontNeedToBeAuthorString,
        ]);

        return array_map(fn (array $row) => $row['id'], $rows);
    }
}
