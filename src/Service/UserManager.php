<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\UserDto;
use App\Entity\Contracts\VisibilityInterface;
use App\Entity\User;
use App\Entity\UserFollowRequest;
use App\Enums\EApplicationStatus;
use App\Event\Instance\InstanceBanEvent;
use App\Event\User\UserApplicationApprovedEvent;
use App\Event\User\UserApplicationRejectedEvent;
use App\Event\User\UserBlockEvent;
use App\Event\User\UserEditedEvent;
use App\Event\User\UserFollowEvent;
use App\Exception\UserCannotBeBanned;
use App\Factory\UserFactory;
use App\Message\ClearDeletedUserMessage;
use App\Message\DeleteImageMessage;
use App\Message\DeleteUserMessage;
use App\Message\Notification\SentNewSignupNotificationMessage;
use App\Message\UserCreatedMessage;
use App\Message\UserUpdatedMessage;
use App\MessageHandler\ClearDeletedUserHandler;
use App\Repository\ImageRepository;
use App\Repository\ReputationRepository;
use App\Repository\UserFollowRepository;
use App\Repository\UserFollowRequestRepository;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\Service\ActivityPub\KeysGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

readonly class UserManager
{
    public function __construct(
        private UserFactory $factory,
        private UserPasswordHasherInterface $passwordHasher,
        private TokenStorageInterface $tokenStorage,
        private RequestStack $requestStack,
        private EventDispatcherInterface $dispatcher,
        private MessageBusInterface $bus,
        private EmailVerifier $verifier,
        private EntityManagerInterface $entityManager,
        private RateLimiterFactoryInterface $userRegisterLimiter,
        private UserFollowRequestRepository $requestRepository,
        private UserFollowRepository $userFollowRepository,
        private UserRepository $userRepository,
        private ImageRepository $imageRepository,
        private Security $security,
        private CacheInterface $cache,
        private ReputationRepository $reputationRepository,
        private SettingsManager $settingsManager,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
    ) {
    }

    public function acceptFollow(User $follower, User $following): void
    {
        if ($request = $this->requestRepository->findOneby(['follower' => $follower, 'following' => $following])) {
            $this->entityManager->remove($request);
        }

        if ($this->userFollowRepository->findOneBy(['follower' => $follower, 'following' => $following])) {
            return;
        }

        $this->follow($follower, $following, false);
    }

    public function follow(User $follower, User $following, $createRequest = true): void
    {
        if ($following->apManuallyApprovesFollowers && $createRequest) {
            if ($this->requestRepository->findOneby(['follower' => $follower, 'following' => $following])) {
                return;
            }

            $request = new UserFollowRequest($follower, $following);
            $this->entityManager->persist($request);
            $this->entityManager->flush();

            $this->dispatcher->dispatch(new UserFollowEvent($follower, $following));

            return;
        }

        $follower->unblock($following);

        $follower->follow($following);

        $this->entityManager->flush();

        $this->dispatcher->dispatch(new UserFollowEvent($follower, $following));
    }

    public function unblock(User $blocker, User $blocked): void
    {
        $blocker->unblock($blocked);

        $this->entityManager->flush();

        $this->dispatcher->dispatch(new UserBlockEvent($blocker, $blocked));
    }

    public function rejectFollow(User $follower, User $following): void
    {
        if ($request = $this->requestRepository->findOneby(['follower' => $follower, 'following' => $following])) {
            $this->entityManager->remove($request);
            $this->entityManager->flush();
        }
    }

    public function block(User $blocker, User $blocked): void
    {
        if ($blocker->isFollowing($blocked)) {
            $this->unfollow($blocker, $blocked);
        }

        $blocker->block($blocked);

        $this->entityManager->flush();

        $this->dispatcher->dispatch(new UserBlockEvent($blocker, $blocked));
    }

    public function unfollow(User $follower, User $following): void
    {
        if ($request = $this->requestRepository->findOneby(['follower' => $follower, 'following' => $following])) {
            $this->entityManager->remove($request);
        }

        $follower->unfollow($following);

        $this->entityManager->flush();

        $this->dispatcher->dispatch(new UserFollowEvent($follower, $following, true));
    }

    public function create(UserDto $dto, bool $verifyUserEmail = true, $rateLimit = true, ?bool $preApprove = null): User
    {
        if ($rateLimit) {
            $limiter = $this->userRegisterLimiter->create($dto->ip);
            if (false === $limiter->consume()->isAccepted()) {
                throw new TooManyRequestsHttpException();
            }
        }
        $status = EApplicationStatus::Approved;
        if (true !== $preApprove && $this->settingsManager->getNewUsersNeedApproval()) {
            $status = EApplicationStatus::Pending;
        }

        $user = new User($dto->email, $dto->username, '', ($dto->isBot) ? 'Service' : 'Person', $dto->apProfileId, $dto->apId, applicationStatus: $status, applicationText: $dto->applicationText);
        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->plainPassword));

        if (!$dto->apId) {
            $user = KeysGenerator::generate($user);
            // default new local users to be discoverable
            $user->apDiscoverable = $dto->discoverable ?? true;
            // default new local users to be indexable
            $user->apIndexable = true;
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        if (!$dto->apId) {
            try {
                $this->bus->dispatch(new SentNewSignupNotificationMessage($user->getId()));
            } catch (\Throwable $e) {
            }
        }

        if ($verifyUserEmail) {
            try {
                $this->bus->dispatch(new UserCreatedMessage($user->getId()));
            } catch (\Throwable $e) {
            }
        }

        return $user;
    }

    public function edit(User $user, UserDto $dto): User
    {
        $this->entityManager->beginTransaction();
        $mailUpdated = false;

        try {
            $user->about = $dto->about;

            $oldAvatar = $user->avatar;
            if ($dto->avatar) {
                $image = $this->imageRepository->find($dto->avatar->id);
                $user->avatar = $image;
            }

            $oldCover = $user->cover;
            if ($dto->cover) {
                $image = $this->imageRepository->find($dto->cover->id);
                $user->cover = $image;
            }

            if ($dto->plainPassword) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $dto->plainPassword));
            }

            if ($dto->email !== $user->email) {
                $mailUpdated = true;
                $user->isVerified = false;
                $user->email = $dto->email;
            }

            if ($this->security->isGranted('edit_profile', $user)) {
                $user->username = $dto->username;
            }

            if ($this->security->isGranted('edit_profile', $user)
                && !$user->isTotpAuthenticationEnabled()
                && $dto->totpSecret) {
                $user->setTotpSecret($dto->totpSecret);
            }

            $user->lastActive = new \DateTime();

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        if ($oldAvatar && $user->avatar !== $oldAvatar) {
            $this->bus->dispatch(new DeleteImageMessage($oldAvatar->getId()));
        }

        if ($oldCover && $user->cover !== $oldCover) {
            $this->bus->dispatch(new DeleteImageMessage($oldCover->getId()));
        }

        if ($mailUpdated) {
            $this->bus->dispatch(new UserUpdatedMessage($user->getId()));
        }

        $this->dispatcher->dispatch(new UserEditedEvent($user->getId()));

        return $user;
    }

    public function delete(User $user): void
    {
        $this->bus->dispatch(new DeleteUserMessage($user->getId()));
    }

    public function createDto(User $user, ?int $reputationPoints = null): UserDto
    {
        return $this->factory->createDto($user, $reputationPoints);
    }

    public function verify(Request $request, User $user): void
    {
        $this->verifier->handleEmailConfirmation($request, $user);
    }

    public function adminUserVerify(User $user): void
    {
        $user->isVerified = true;

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function toggleTheme(User $user): void
    {
        $user->toggleTheme();

        $this->entityManager->flush();
    }

    public function logout(): void
    {
        $this->tokenStorage->setToken(null);
        $this->requestStack->getSession()->invalidate();
    }

    public function ban(User $user, ?User $bannedBy, ?string $reason): void
    {
        if ($user->isAdmin() || $user->isModerator()) {
            throw new UserCannotBeBanned();
        }

        $user->isBanned = true;
        $user->banReason = $reason;

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new InstanceBanEvent($user, $bannedBy, $reason));
    }

    public function unban(User $user, ?User $bannedBy, ?string $reason): void
    {
        $user->isBanned = false;
        $user->banReason = $reason;

        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->eventDispatcher->dispatch(new InstanceBanEvent($user, $bannedBy, $reason));
    }

    public function detachAvatar(User $user): void
    {
        if (!$user->avatar) {
            return;
        }

        $image = $user->avatar->getId();

        $user->avatar = null;

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->bus->dispatch(new DeleteImageMessage($image));
    }

    public function detachCover(User $user): void
    {
        if (!$user->cover) {
            return;
        }

        $image = $user->cover->getId();

        $user->cover = null;

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->bus->dispatch(new DeleteImageMessage($image));
    }

    /**
     * @param User $user        the user that should be deleted
     * @param bool $immediately if true we will immediately dispatch a DeleteMessage,
     *                          if false the account will be marked for deletion in 30 days.
     *                          Our scheduler will then take care of deleting the account via ClearDeletedUserMessage and ClearDeletedUserHandler
     *
     * @see ClearDeletedUserMessage
     * @see ClearDeletedUserHandler
     */
    public function deleteRequest(User $user, bool $immediately): void
    {
        if (!$immediately) {
            $user->softDelete();

            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } else {
            $this->delete($user);
        }
    }

    /**
     * If the user is marked for deletion this will remove that mark and restore the user.
     *
     * @param User $user the user to be checked
     */
    public function removeDeleteRequest(User $user): void
    {
        if (null !== $user->markedForDeletionAt) {
            $user->restore();

            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }
    }

    /**
     * Suspend user.
     */
    public function suspend(User $user): void
    {
        $user->visibility = VisibilityInterface::VISIBILITY_TRASHED;

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * Unsuspend user.
     */
    public function unsuspend(User $user): void
    {
        $user->visibility = VisibilityInterface::VISIBILITY_VISIBLE;

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function removeFollowing(User $user): void
    {
        foreach ($user->follows as $follow) {
            $this->unfollow($user, $follow->following);
        }
    }

    /**
     * Get user reputation total add it behind a cache.
     */
    public function getReputationTotal(User $user): int
    {
        return $this->cache->get(
            "user_reputation_{$user->getId()}",
            function (ItemInterface $item) use ($user) {
                $item->expiresAfter(60);

                return $this->reputationRepository->getUserReputationTotal($user);
            }
        );
    }

    /**
     * @return User[]
     */
    public function getUsersMarkedForDeletionBefore(?\DateTime $dateTime = null): array
    {
        $dateTime ??= new \DateTime();

        return $this->userRepository->createQueryBuilder('u')
            ->where('u.markedForDeletionAt <= :datetime')
            ->setParameter(':datetime', $dateTime)
            ->getQuery()
            ->getResult();
    }

    public function rejectUserApplication(User $user): void
    {
        if (EApplicationStatus::Rejected === $user->getApplicationStatus()) {
            return;
        }
        $user->setApplicationStatus(EApplicationStatus::Rejected);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->logger->debug('Rejecting application for {u}', ['u' => $user->username]);
        $this->eventDispatcher->dispatch(new UserApplicationRejectedEvent($user));
    }

    public function approveUserApplication(User $user): void
    {
        if (EApplicationStatus::Approved === $user->getApplicationStatus()) {
            return;
        }
        $user->setApplicationStatus(EApplicationStatus::Approved);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->logger->debug('Approving application for {u}', ['u' => $user->username]);
        $this->eventDispatcher->dispatch(new UserApplicationApprovedEvent($user));
    }

    public function getAllInboxesOfInteractions(User $user): array
    {
        $sql = '
                -- remote magazines the user is subscribed to
                SELECT res.ap_inbox_url FROM magazine_subscription
                    INNER JOIN public.magazine res ON magazine_subscription.magazine_id = res.id AND res.ap_id IS NOT NULL
                    INNER JOIN public.user u on magazine_subscription.user_id = u.id
                        WHERE u.id = :id
            UNION DISTINCT
                -- local magazines the user is subscribed to -> their remote subscribers
                SELECT res.ap_inbox_url FROM magazine_subscription ms
                    INNER JOIN public.magazine m ON ms.magazine_id = m.id AND m.ap_id IS NULL
                    INNER JOIN public.user res ON ms.user_id = res.id AND res.ap_id IS NOT NULL
                    WHERE EXISTS (SELECT id FROM magazine_subscription ms2 WHERE ms2.magazine_id=m.id AND user_id = :id)
            UNION DISTINCT
                -- users followed by the user
                SELECT res.ap_inbox_url FROM user_follow
                    INNER JOIN public.user res on user_follow.follower_id = res.id AND res.ap_id IS NOT NULL
                        WHERE user_follow.following_id = :id
            UNION DISTINCT
                -- users following the user
                SELECT res.ap_inbox_url FROM user_follow
                    INNER JOIN public.user res on user_follow.following_id = res.id AND res.ap_id IS NOT NULL
                        WHERE user_follow.follower_id = :id
            UNION DISTINCT
                -- remote magazines the user posted threads to
                SELECT res.ap_inbox_url FROM entry
                    INNER JOIN public.magazine res on entry.magazine_id = res.id AND res.ap_id IS NOT NULL
                        WHERE entry.user_id = :id
            UNION DISTINCT
                -- remote magazines the user posted thread comments to
                SELECT res.ap_inbox_url FROM entry_comment
                    INNER JOIN public.magazine res on entry_comment.magazine_id = res.id AND res.ap_id IS NOT NULL
                        WHERE entry_comment.user_id = :id
            UNION DISTINCT
                -- remote magazines the user posted microblogs to
                SELECT res.ap_inbox_url FROM post
                    INNER JOIN public.magazine res on post.magazine_id = res.id AND res.ap_id IS NOT NULL
                        WHERE post.user_id = :id
            UNION DISTINCT
                -- remote magazine the user posted microblog comments to
                SELECT res.ap_inbox_url FROM post_comment
                    INNER JOIN public.magazine res on post_comment.magazine_id = res.id AND res.ap_id IS NOT NULL
                        WHERE post_comment.user_id = :id
            UNION DISTINCT
                -- local magazines the user posted threads to -> their subscribers
                SELECT res.ap_inbox_url FROM entry
                    INNER JOIN magazine m on entry.magazine_id = m.id AND m.ap_id IS NULL
                    INNER JOIN magazine_subscription ms ON m.id = ms.magazine_id
                    INNER JOIN public.user res ON ms.user_id = res.id AND res.ap_id IS NOT NULL
                        WHERE entry.user_id = :id
            UNION DISTINCT
                -- local magazines the user posted thread comments to -> their subscribers
                SELECT res.ap_inbox_url FROM entry_comment
                    INNER JOIN magazine m on entry_comment.magazine_id = m.id AND m.ap_id IS NULL
                    INNER JOIN magazine_subscription ms ON m.id = ms.magazine_id
                    INNER JOIN public.user res ON ms.user_id = res.id AND res.ap_id IS NOT NULL
                        WHERE entry_comment.user_id = :id
            UNION DISTINCT
                -- local magazines the user posted microblogs to -> their subscribers
                SELECT res.ap_inbox_url FROM post
                    INNER JOIN magazine m on post.magazine_id = m.id AND m.ap_id IS NULL
                    INNER JOIN magazine_subscription ms ON m.id = ms.magazine_id
                    INNER JOIN public.user res ON ms.user_id = res.id AND res.ap_id IS NOT NULL
                        WHERE post.user_id = :id
            UNION DISTINCT
                -- local magazine the user posted microblog comments to -> their subscribers
                SELECT res.ap_inbox_url FROM post_comment
                    INNER JOIN magazine m on post_comment.magazine_id = m.id AND m.ap_id IS NULL
                    INNER JOIN magazine_subscription ms ON m.id = ms.magazine_id
                    INNER JOIN public.user res ON ms.user_id = res.id AND res.ap_id IS NOT NULL
                        WHERE post_comment.user_id = :id
            UNION DISTINCT
                -- author of micro blogs the user commented on
                SELECT res.ap_inbox_url FROM post_comment
                    INNER JOIN public.post p on post_comment.post_id = p.id
                    INNER JOIN public.user res on p.user_id = res.id AND res.ap_id IS NOT NULL
                        WHERE post_comment.user_id = :id
            UNION DISTINCT
                -- author of the microblog comment the user commented on
                SELECT res.ap_inbox_url FROM post_comment pc1
                    INNER JOIN post_comment pc2 ON pc1.parent_id=pc2.id
                    INNER JOIN public.user res ON pc2.user_id=res.id AND res.ap_id IS NOT NULL
                        WHERE pc1.user_id = :id
            UNION DISTINCT
                -- author of threads the user commented on
                SELECT res.ap_inbox_url FROM entry_comment
                    INNER JOIN public.entry e on entry_comment.entry_id = e.id
                    INNER JOIN public.user res on e.user_id = res.id AND res.ap_id IS NOT NULL
                        WHERE entry_comment.user_id = :id
            UNION DISTINCT
                -- author of thread comments the user commented on
                SELECT res.ap_inbox_url FROM entry_comment ec1
                    INNER JOIN entry_comment ec2 ON ec1.parent_id=ec2.id
                    INNER JOIN public.user res ON ec2.user_id=res.id AND res.ap_id IS NOT NULL
                        WHERE ec1.user_id = :id

            UNION DISTINCT
                -- author of thread the user voted on
                SELECT res.ap_inbox_url FROM entry_vote
                    INNER JOIN public.user res on entry_vote.author_id = res.id AND res.ap_id IS NOT NULL
                        WHERE entry_vote.user_id = :id
            UNION DISTINCT
                -- magazine of thread the user voted on
                SELECT res.ap_inbox_url FROM entry_vote
                    INNER JOIN entry ON entry_vote.entry_id = entry.id
                    INNER JOIN magazine res ON entry.magazine_id=res.id AND res.ap_id IS NOT NULL
                        WHERE entry_vote.user_id = :id

            UNION DISTINCT
                -- author of thread comment the user voted on
                SELECT res.ap_inbox_url FROM entry_comment_vote
                    INNER JOIN public.user res on entry_comment_vote.author_id = res.id AND res.ap_id IS NOT NULL
                        WHERE entry_comment_vote.user_id = :id
            UNION DISTINCT
                -- magazine of thread comment the user voted on
                SELECT res.ap_inbox_url FROM entry_comment_vote
                    INNER JOIN entry_comment ON entry_comment_vote.comment_id = entry_comment.id
                    INNER JOIN magazine res ON entry_comment.magazine_id=res.id AND res.ap_id IS NOT NULL
                        WHERE entry_comment_vote.user_id = :id
            UNION DISTINCT
                -- author of microblog the user voted on
                SELECT res.ap_inbox_url FROM post_vote
                    INNER JOIN public.user res on post_vote.author_id = res.id AND res.ap_id IS NOT NULL
                        WHERE post_vote.user_id = :id
            UNION DISTINCT
                -- magazine of microblog the user voted on
                SELECT res.ap_inbox_url FROM post_vote
                    INNER JOIN entry ON post_vote.post_id = entry.id
                    INNER JOIN magazine res ON entry.magazine_id=res.id AND res.ap_id IS NOT NULL
                        WHERE post_vote.user_id = :id

            UNION DISTINCT
                -- author of microblog comment the user voted on
                SELECT res.ap_inbox_url FROM post_comment_vote
                    INNER JOIN public.user res on post_comment_vote.author_id = res.id AND res.ap_id IS NOT NULL
                        WHERE post_comment_vote.user_id = :id
            UNION DISTINCT
                -- magazine of microblog comment the user voted on
                SELECT res.ap_inbox_url FROM post_comment_vote
                    INNER JOIN post_comment ON post_comment_vote.comment_id = post_comment.id
                    INNER JOIN magazine res ON post_comment.magazine_id=res.id AND res.ap_id IS NOT NULL
                        WHERE post_comment_vote.user_id = :id


            UNION DISTINCT
                -- voters of threads of the user
                SELECT res.ap_inbox_url FROM entry_vote
                    INNER JOIN public.user res on entry_vote.user_id = res.id AND res.ap_id IS NOT NULL
                        WHERE entry_vote.author_id = :id

            UNION DISTINCT
                -- voters of thread comments of the user
                SELECT res.ap_inbox_url FROM entry_comment_vote
                    INNER JOIN public.user res on entry_comment_vote.user_id = res.id AND res.ap_id IS NOT NULL
                        WHERE entry_comment_vote.author_id = :id

            UNION DISTINCT
                -- voters of microblog of the user
                SELECT res.ap_inbox_url FROM post_vote
                    INNER JOIN public.user res on post_vote.user_id = res.id AND res.ap_id IS NOT NULL
                        WHERE post_vote.author_id = :id

            UNION DISTINCT
                -- voters of microblog comments of the user
                SELECT res.ap_inbox_url FROM post_comment_vote
                    INNER JOIN public.user res on post_comment_vote.user_id = res.id
                        WHERE post_comment_vote.author_id = :id AND res.ap_id IS NOT NULL

            UNION DISTINCT
                -- favourites of entries of the user
                SELECT res.ap_inbox_url FROM favourite f
                    INNER JOIN public.user res on f.user_id = res.id AND res.ap_id IS NOT NULL
                    INNER JOIN entry e ON f.entry_id = e.id
                        WHERE e.user_id = :id
            UNION DISTINCT
                -- favourites of entry comments of the user
                SELECT res.ap_inbox_url FROM favourite f
                    INNER JOIN public.user res on f.user_id = res.id AND res.ap_id IS NOT NULL
                    INNER JOIN entry_comment ec ON f.entry_comment_id = ec.id
                        WHERE ec.user_id = :id
            UNION DISTINCT
                -- favourites of posts of the user
                SELECT res.ap_inbox_url FROM favourite f
                    INNER JOIN public.user res on f.user_id = res.id AND res.ap_id IS NOT NULL
                    INNER JOIN post p ON f.post_id = p.id
                        WHERE p.user_id = :id
            UNION DISTINCT
                -- favourites of post comments of the user
                SELECT res.ap_inbox_url FROM favourite f
                    INNER JOIN public.user res on f.user_id = res.id AND res.ap_id IS NOT NULL
                    INNER JOIN post_comment pc ON f.entry_id = pc.id
                        WHERE pc.user_id = :id

            UNION DISTINCT
                -- favourites of the user: entries
                SELECT res.ap_inbox_url FROM favourite f
                    INNER JOIN public.user u on f.user_id = u.id AND f.user_id = :id
                    INNER JOIN entry e ON f.entry_id = e.id
                    INNER JOIN public.user res ON e.user_id=res.id AND res.ap_id IS NOT NULL
            UNION DISTINCT
                -- favourites of the user: entry comments
                SELECT res.ap_inbox_url FROM favourite f
                    INNER JOIN public.user u on f.user_id = u.id AND f.user_id = :id
                    INNER JOIN entry_comment ec ON f.entry_comment_id = ec.id
                    INNER JOIN public.user res ON ec.user_id=res.id AND res.ap_id IS NOT NULL
            UNION DISTINCT
                -- favourites of the user: posts
                SELECT res.ap_inbox_url FROM favourite f
                    INNER JOIN public.user u on f.user_id = u.id AND f.user_id = :id
                    INNER JOIN post p ON f.post_id = p.id
                    INNER JOIN public.user res ON p.user_id=res.id AND res.ap_id IS NOT NULL
            UNION DISTINCT
                -- favourites of the user: post comments
                SELECT res.ap_inbox_url FROM favourite f
                    INNER JOIN public.user u on f.user_id = u.id AND f.user_id = :id
                    INNER JOIN post_comment pc ON f.post_comment_id = pc.id
                    INNER JOIN public.user res ON pc.user_id=res.id AND res.ap_id IS NOT NULL

            UNION DISTINCT
                -- favourites of the user: entries -> their magazine
                SELECT res.ap_inbox_url FROM favourite f
                    INNER JOIN public.user u on f.user_id = u.id AND f.user_id = :id
                    INNER JOIN entry e ON f.entry_id = e.id
                    INNER JOIN magazine res ON e.magazine_id=res.id AND res.ap_id IS NOT NULL
            UNION DISTINCT
                -- favourites of the user: entry comments -> their magazine
                SELECT res.ap_inbox_url FROM favourite f
                    INNER JOIN public.user u on f.user_id = u.id AND f.user_id = :id
                    INNER JOIN entry_comment ec ON f.entry_comment_id = ec.id
                    INNER JOIN magazine res ON ec.magazine_id=res.id AND res.ap_id IS NOT NULL
            UNION DISTINCT
                -- favourites of the user: posts -> their magazine
                SELECT res.ap_inbox_url FROM favourite f
                    INNER JOIN public.user u on f.user_id = u.id AND f.user_id = :id
                    INNER JOIN post p ON f.post_id = p.id
                    INNER JOIN magazine res ON p.magazine_id=res.id AND res.ap_id IS NOT NULL
            UNION DISTINCT
                -- favourites of the user: post comments -> their magazine
                SELECT res.ap_inbox_url FROM favourite f
                    INNER JOIN public.user u on f.user_id = u.id AND f.user_id = :id
                    INNER JOIN post_comment pc ON f.post_comment_id = pc.id
                    INNER JOIN magazine res ON pc.magazine_id=res.id AND res.ap_id IS NOT NULL
        ';

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('ap_inbox_url', 0);

        $result = $this->entityManager->createNativeQuery($sql, $rsm)
            ->setParameter(':id', $user->getId())
            // ->execute([":id" => $user->getId()]);
            ->getScalarResult();

        return array_filter(array_map(fn ($row) => $row[0], $result));
    }

    /**
     * This method will return all image paths that the user **owns**,
     * meaning that that image belongs only to posts from the user and not to anybody else's.
     *
     * @return string[]
     */
    public function getAllImageFilePathsOfUser(User $user): array
    {
        $sql = '
            SELECT i1.file_path FROM entry e INNER JOIN image i1 ON e.image_id = i1.id
                WHERE e.user_id = :userId AND i1.file_path IS NOT NULL
                    AND NOT EXISTS (SELECT id FROM entry e2 WHERE e2.user_id <> :userId AND e2.image_id = i1.id)
                    AND NOT EXISTS (SELECT id FROM post p2 WHERE p2.user_id <> :userId AND p2.image_id = i1.id)
                    AND NOT EXISTS (SELECT id FROM entry_comment ec2 WHERE ec2.user_id <> :userId AND ec2.image_id = i1.id)
                    AND NOT EXISTS (SELECT id FROM post_comment pc2 WHERE pc2.user_id <> :userId AND pc2.image_id = i1.id)
            UNION DISTINCT
            SELECT i2.file_path FROM post p INNER JOIN image i2 ON p.image_id = i2.id
                WHERE p.user_id = :userId AND i2.file_path IS NOT NULL
                    AND NOT EXISTS (SELECT id FROM entry e2 WHERE e2.user_id <> :userId AND e2.image_id = i2.id)
                    AND NOT EXISTS (SELECT id FROM post p2 WHERE p2.user_id <> :userId AND p2.image_id = i2.id)
                    AND NOT EXISTS (SELECT id FROM entry_comment ec2 WHERE ec2.user_id <> :userId AND ec2.image_id = i2.id)
                    AND NOT EXISTS (SELECT id FROM post_comment pc2 WHERE pc2.user_id <> :userId AND pc2.image_id = i2.id)
            UNION DISTINCT
            SELECT i3.file_path FROM entry_comment ec INNER JOIN image i3 ON ec.image_id = i3.id
                WHERE ec.user_id = :userId AND i3.file_path IS NOT NULL
                    AND NOT EXISTS (SELECT id FROM entry e2 WHERE e2.user_id <> :userId AND e2.image_id = i3.id)
                    AND NOT EXISTS (SELECT id FROM post p2 WHERE p2.user_id <> :userId AND p2.image_id = i3.id)
                    AND NOT EXISTS (SELECT id FROM entry_comment ec2 WHERE ec2.user_id <> :userId AND ec2.image_id = i3.id)
                    AND NOT EXISTS (SELECT id FROM post_comment pc2 WHERE pc2.user_id <> :userId AND pc2.image_id = i3.id)
            UNION DISTINCT
            SELECT i4.file_path FROM post_comment pc INNER JOIN image i4 ON pc.image_id = i4.id
                WHERE pc.user_id = :userId AND i4.file_path IS NOT NULL
                    AND NOT EXISTS (SELECT id FROM entry e2 WHERE e2.user_id <> :userId AND e2.image_id = i4.id)
                    AND NOT EXISTS (SELECT id FROM post p2 WHERE p2.user_id <> :userId AND p2.image_id = i4.id)
                    AND NOT EXISTS (SELECT id FROM entry_comment ec2 WHERE ec2.user_id <> :userId AND ec2.image_id = i4.id)
                    AND NOT EXISTS (SELECT id FROM post_comment pc2 WHERE pc2.user_id <> :userId AND pc2.image_id = i4.id)
        ';
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('file_path', 0);

        $result = $this->entityManager->createNativeQuery($sql, $rsm)
            ->setParameter(':userId', $user->getId())
            ->getScalarResult();

        return array_filter(array_map(fn ($row) => $row[0], $result));
    }
}
