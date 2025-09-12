<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Magazine;
use App\Entity\Moderator;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\EntryRepository;
use App\Repository\MagazineRepository;
use App\Repository\TagLinkRepository;
use App\Service\ActivityPub\ActivityJsonBuilder;
use App\Service\ActivityPub\ContextsProvider;
use App\Service\ActivityPub\Wrapper\CollectionInfoWrapper;
use App\Service\ActivityPub\Wrapper\CollectionItemsWrapper;
use App\Service\ActivityPubManager;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CollectionFactory
{
    public function __construct(
        private readonly ContextsProvider $contextsProvider,
        private readonly CollectionInfoWrapper $collectionInfoWrapper,
        private readonly CollectionItemsWrapper $collectionItemsWrapper,
        private readonly ActivityJsonBuilder $activityJsonBuilder,
        private readonly ActivityRepository $activityRepository,
        private readonly ActivityPubManager $manager,
        private readonly MagazineRepository $magazineRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EntryRepository $entryRepository,
        private readonly TagLinkRepository $tagLinkRepository,
        private readonly EntryPageFactory $entryFactory,
    ) {
    }

    #[ArrayShape([
        '@context' => 'string',
        'type' => 'string',
        'id' => 'string',
        'first' => 'string',
        'totalItems' => 'int',
    ])]
    public function getUserOutboxCollection(User $user, bool $includeContext = true): array
    {
        $fanta = $this->activityRepository->getOutboxActivitiesOfUser($user);

        return $this->collectionInfoWrapper->build(
            'ap_user_outbox',
            ['username' => $user->username],
            $fanta->count(),
            $includeContext,
        );
    }

    #[ArrayShape([
        '@context' => 'string',
        'type' => 'string',
        'partOf' => 'string',
        'id' => 'string',
        'totalItems' => 'int',
        'orderedItems' => 'array',
    ])]
    public function getUserOutboxCollectionItems(User $user, int $page, bool $includeContext = true): array
    {
        $activity = $this->activityRepository->getOutboxActivitiesOfUser($user);
        $activity->setCurrentPage($page);
        $activity->setMaxPerPage(10);

        $items = [];
        foreach ($activity as $item) {
            $json = $this->activityJsonBuilder->buildActivityJson($item);
            unset($json['@context']);
            $items[] = $json;
        }

        return $this->collectionItemsWrapper->build(
            'ap_user_outbox',
            ['username' => $user->username],
            $activity,
            $items,
            $page,
            $includeContext,
        );
    }

    #[ArrayShape([
        '@context' => 'array',
        'type' => 'string',
        'id' => 'string',
        'totalItems' => 'int',
        'orderedItems' => 'array',
    ])]
    public function getMagazineModeratorCollection(Magazine $magazine, bool $includeContext = true): array
    {
        $moderators = $this->magazineRepository->findModerators($magazine, perPage: $magazine->moderators->count());

        $items = [];
        foreach ($moderators->getCurrentPageResults() as /* @var Moderator $mod */ $mod) {
            $actor = $mod->user;
            $items[] = $this->manager->getActorProfileId($actor);
        }

        $result = [
            '@context' => $this->contextsProvider->referencedContexts(),
            'type' => 'OrderedCollection',
            'id' => $this->urlGenerator->generate('ap_magazine_moderators', ['name' => $magazine->name], UrlGeneratorInterface::ABSOLUTE_URL),
            'totalItems' => \sizeof($items),
            'orderedItems' => $items,
        ];

        if (!$includeContext) {
            unset($result['@context']);
        }

        return $result;
    }

    #[ArrayShape([
        '@context' => 'array',
        'type' => 'string',
        'id' => 'string',
        'totalItems' => 'int',
        'orderedItems' => 'array',
    ])]
    public function getMagazinePinnedCollection(Magazine $magazine, bool $includeContext = true): array
    {
        $pinned = $this->entryRepository->findPinned($magazine);

        $items = [];
        foreach ($pinned as $entry) {
            $items[] = $this->entryFactory->create($entry, $this->tagLinkRepository->getTagsOfEntry($entry));
        }

        $result = [
            '@context' => $this->contextsProvider->referencedContexts(),
            'type' => 'OrderedCollection',
            'id' => $this->urlGenerator->generate('ap_magazine_pinned', ['name' => $magazine->name], UrlGeneratorInterface::ABSOLUTE_URL),
            'totalItems' => \sizeof($items),
            'orderedItems' => $items,
        ];

        if (!$includeContext) {
            unset($result['@context']);
        }

        return $result;
    }
}
