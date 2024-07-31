<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\MagazineBanDto;
use App\DTO\MagazineDto;
use App\DTO\MagazineThemeDto;
use App\DTO\ModeratorDto;
use App\Entity\Magazine;
use App\Entity\MagazineBan;
use App\Entity\MagazineOwnershipRequest;
use App\Entity\Moderator;
use App\Entity\ModeratorRequest;
use App\Entity\User;
use App\Event\Magazine\MagazineBanEvent;
use App\Event\Magazine\MagazineBlockedEvent;
use App\Event\Magazine\MagazineModeratorAddedEvent;
use App\Event\Magazine\MagazineModeratorRemovedEvent;
use App\Event\Magazine\MagazineSubscribedEvent;
use App\Event\Magazine\MagazineUpdatedEvent;
use App\Exception\UserCannotBeBanned;
use App\Factory\MagazineFactory;
use App\Message\DeleteImageMessage;
use App\Message\MagazinePurgeMessage;
use App\Repository\ImageRepository;
use App\Repository\MagazineSubscriptionRepository;
use App\Repository\MagazineSubscriptionRequestRepository;
use App\Service\ActivityPub\KeysGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Cache\CacheInterface;
use Webmozart\Assert\Assert;

class MagazineManager
{
    public function __construct(
        private readonly MagazineFactory $factory,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly RateLimiterFactory $magazineLimiter,
        private readonly CacheInterface $cache,
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $entityManager,
        private readonly MagazineSubscriptionRequestRepository $requestRepository,
        private readonly MagazineSubscriptionRepository $subscriptionRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ImageRepository $imageRepository,
        private readonly SettingsManager $settingsManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function create(MagazineDto $dto, ?User $user, bool $rateLimit = true): Magazine
    {
        if (!$dto->apId && true === $this->settingsManager->get('MBIN_RESTRICT_MAGAZINE_CREATION') && !$user->isAdmin() && !$user->isModerator()) {
            throw new AccessDeniedException();
        }

        if ($rateLimit) {
            $limiter = $this->magazineLimiter->create($dto->ip);
            if (false === $limiter->consume()->isAccepted()) {
                throw new TooManyRequestsHttpException();
            }
        }

        $magazine = $this->factory->createFromDto($dto, $user);
        $magazine->apId = $dto->apId;
        $magazine->apProfileId = $dto->apProfileId;
        $magazine->apFeaturedUrl = $dto->apFeaturedUrl;

        if (!$dto->apId) {
            $magazine = KeysGenerator::generate($magazine);
            $magazine->apProfileId = $this->urlGenerator->generate(
                'ap_magazine',
                ['name' => $magazine->name],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }

        $this->entityManager->persist($magazine);
        $this->entityManager->flush();

        $this->logger->debug('created magazine with name {n}, apId {id} and public url {url}', ['n' => $magazine->name, 'id' => $magazine->apId, 'url' => $magazine->apProfileId]);

        if (!$dto->apId) {
            $this->subscribe($magazine, $user);
        }

        return $magazine;
    }

    public function acceptFollow(User $user, Magazine $magazine): void
    {
        if ($request = $this->requestRepository->findOneby(['user' => $user, 'magazine' => $magazine])) {
            $this->entityManager->remove($request);
        }

        if ($this->subscriptionRepository->findOneBy(['user' => $user, 'magazine' => $magazine])) {
            return;
        }

        $this->subscribe($magazine, $user, false);
    }

    public function subscribe(Magazine $magazine, User $user, $createRequest = true): void
    {
        $user->unblockMagazine($magazine);

        //        if ($magazine->apId && $createRequest) {
        //            if ($this->requestRepository->findOneby(['user' => $user, 'magazine' => $magazine])) {
        //                return;
        //            }
        //
        //            $request = new MagazineSubscriptionRequest($user, $magazine);
        //            $this->entityManager->persist($request);
        //            $this->entityManager->flush();
        //
        //            $this->dispatcher->dispatch(new MagazineSubscribedEvent($magazine, $user));
        //
        //            return;
        //        }

        $magazine->subscribe($user);

        $this->entityManager->flush();

        $this->dispatcher->dispatch(new MagazineSubscribedEvent($magazine, $user));
    }

    public function edit(Magazine $magazine, MagazineDto $dto, User $editedBy): Magazine
    {
        Assert::same($magazine->name, $dto->name);

        $magazine->title = $dto->title;
        $magazine->description = $dto->description;
        $magazine->rules = $dto->rules;
        $magazine->isAdult = $dto->isAdult;
        $magazine->postingRestrictedToMods = $dto->isPostingRestrictedToMods;

        $this->entityManager->flush();

        $this->dispatcher->dispatch(new MagazineUpdatedEvent($magazine, $editedBy));

        return $magazine;
    }

    public function delete(Magazine $magazine): void
    {
        $magazine->softDelete();

        $this->entityManager->flush();
    }

    public function restore(Magazine $magazine): void
    {
        $magazine->restore();

        $this->entityManager->flush();
    }

    public function purge(Magazine $magazine, bool $contentOnly = false): void
    {
        $this->bus->dispatch(new MagazinePurgeMessage($magazine->getId(), $contentOnly));
    }

    public function createDto(Magazine $magazine): MagazineDto
    {
        return $this->factory->createDto($magazine);
    }

    public function block(Magazine $magazine, User $user): void
    {
        if ($magazine->isSubscribed($user)) {
            $this->unsubscribe($magazine, $user);
        }

        $user->blockMagazine($magazine);

        $this->entityManager->flush();

        $this->dispatcher->dispatch(new MagazineBlockedEvent($magazine, $user));
    }

    public function unsubscribe(Magazine $magazine, User $user): void
    {
        $magazine->unsubscribe($user);

        $this->entityManager->flush();

        $this->dispatcher->dispatch(new MagazineSubscribedEvent($magazine, $user, true));
    }

    public function unblock(Magazine $magazine, User $user): void
    {
        $user->unblockMagazine($magazine);

        $this->entityManager->flush();

        $this->dispatcher->dispatch(new MagazineBlockedEvent($magazine, $user));
    }

    public function ban(Magazine $magazine, User $user, User $bannedBy, MagazineBanDto $dto): ?MagazineBan
    {
        if ($user->isAdmin() || $magazine->userIsModerator($user)) {
            throw new UserCannotBeBanned();
        }

        Assert::nullOrGreaterThan($dto->expiredAt, new \DateTime());

        $ban = $magazine->addBan($user, $bannedBy, $dto->reason, $dto->expiredAt);

        if (!$ban) {
            return null;
        }

        $this->entityManager->flush();

        $this->dispatcher->dispatch(new MagazineBanEvent($ban));

        return $ban;
    }

    public function unban(Magazine $magazine, User $user): ?MagazineBan
    {
        if (!$magazine->isBanned($user)) {
            return null;
        }

        $ban = $magazine->unban($user);

        $this->entityManager->flush();

        $this->dispatcher->dispatch(new MagazineBanEvent($ban));

        return $ban;
    }

    public function addModerator(ModeratorDto $dto, ?bool $isOwner = false): void
    {
        $magazine = $dto->magazine;

        $magazine->addModerator(new Moderator($magazine, $dto->user, $dto->addedBy, $isOwner, true));

        $this->entityManager->flush();

        $this->clearCommentsCache($dto->user);
        $this->dispatcher->dispatch(new MagazineModeratorAddedEvent($magazine, $dto->user, $dto->addedBy));
    }

    private function clearCommentsCache(User $user)
    {
        $this->cache->invalidateTags([
            'post_comments_user_'.$user->getId(),
            'entry_comments_user_'.$user->getId(),
        ]);
    }

    public function removeModerator(Moderator $moderator, ?User $removedBy): void
    {
        $user = $moderator->user;

        $this->entityManager->remove($moderator);
        $this->entityManager->flush();

        $this->clearCommentsCache($user);
        $this->dispatcher->dispatch(new MagazineModeratorRemovedEvent($moderator->magazine, $moderator->user, $removedBy));
    }

    public function changeTheme(MagazineThemeDto $dto): Magazine
    {
        $magazine = $dto->magazine;

        if ($dto->icon && $magazine->icon?->getId() !== $dto->icon->id) {
            $magazine->icon = $this->imageRepository->find($dto->icon->id);
        }

        // custom css
        $customCss = $dto->customCss;

        // add custom background to custom CSS if defined
        $background = null;
        if ($dto->backgroundImage) {
            $background = match ($dto->backgroundImage) {
                'shape1' => '/build/images/shape.png',
                'shape2' => '/build/images/shape2.png',
                default => null,
            };

            $background = $background ? "#middle { background: url($background); height: 100%; }" : null;
            if ($background) {
                $customCss = \sprintf('%s %s', $customCss, $background);
            }
        }

        $magazine->customCss = $customCss;
        $this->entityManager->persist($magazine);
        $this->entityManager->flush();

        return $magazine;
    }

    public function detachIcon(Magazine $magazine): void
    {
        if (!$magazine->icon) {
            return;
        }

        $image = $magazine->icon->getId();

        $magazine->icon = null;

        $this->entityManager->persist($magazine);
        $this->entityManager->flush();

        $this->bus->dispatch(new DeleteImageMessage($image));
    }

    public function removeSubscriptions(Magazine $magazine): void
    {
        foreach ($magazine->subscriptions as $subscription) {
            $this->unsubscribe($subscription->magazine, $subscription->user);
        }
    }

    public function toggleOwnershipRequest(Magazine $magazine, User $user): void
    {
        $request = $this->entityManager->getRepository(MagazineOwnershipRequest::class)->findOneBy([
            'magazine' => $magazine,
            'user' => $user,
        ]);

        if ($request) {
            $this->entityManager->remove($request);
            $this->entityManager->flush();

            return;
        }

        $request = new MagazineOwnershipRequest($magazine, $user);

        $this->entityManager->persist($request);
        $this->entityManager->flush();
    }

    public function acceptOwnershipRequest(Magazine $magazine, User $user, ?User $addedBy): void
    {
        $owner = $magazine->getOwnerModerator();
        if ($owner) {
            $this->removeModerator($owner, $addedBy);
        }

        $this->addModerator(new ModeratorDto($magazine, $user, $addedBy), true);

        $request = $this->entityManager->getRepository(MagazineOwnershipRequest::class)->findOneBy([
            'magazine' => $magazine,
            'user' => $user,
        ]);

        $this->entityManager->remove($request);
        $this->entityManager->flush();
    }

    public function toggleModeratorRequest(Magazine $magazine, User $user): void
    {
        $request = $this->entityManager->getRepository(ModeratorRequest::class)->findOneBy([
            'magazine' => $magazine,
            'user' => $user,
        ]);

        if ($request) {
            $this->entityManager->remove($request);
            $this->entityManager->flush();

            return;
        }

        $request = new ModeratorRequest($magazine, $user);

        $this->entityManager->persist($request);
        $this->entityManager->flush();
    }

    public function acceptModeratorRequest(Magazine $magazine, User $user, ?User $addedBy): void
    {
        $this->addModerator(new ModeratorDto($magazine, $user, $addedBy));

        $request = $this->entityManager->getRepository(ModeratorRequest::class)->findOneBy([
            'magazine' => $magazine,
            'user' => $user,
        ]);

        $this->entityManager->remove($request);
        $this->entityManager->flush();
    }
}
