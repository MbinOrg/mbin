<?php

declare(strict_types=1);

namespace App\Controller\ActivityPub\Magazine;

use App\Controller\AbstractController;
use App\Entity\Magazine;
use App\Repository\MagazineSubscriptionRepository;
use App\Service\ActivityPub\Wrapper\CollectionInfoWrapper;
use App\Service\ActivityPub\Wrapper\CollectionItemsWrapper;
use App\Service\ActivityPubManager;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class MagazineFollowersController extends AbstractController
{
    public function __construct(
        private readonly ActivityPubManager $manager,
        private readonly CollectionInfoWrapper $collectionInfoWrapper,
        private readonly CollectionItemsWrapper $collectionItemsWrapper,
        private readonly MagazineSubscriptionRepository $magazineSubscriptionRepository,
    ) {
    }

    public function __invoke(Magazine $magazine, Request $request): JsonResponse
    {
        if ($magazine->apId) {
            throw $this->createNotFoundException();
        }

        if (!$request->get('page')) {
            $data = $this->collectionInfoWrapper->build('ap_magazine_followers', ['name' => $magazine->name], $magazine->subscriptionsCount);
        } else {
            $data = $this->getCollectionItems($magazine, (int) $request->get('page'));
        }

        $response = new JsonResponse($data);
        $response->headers->set('Content-Type', 'application/activity+json');

        return $response;
    }

    #[ArrayShape([
        '@context' => 'string',
        'type' => 'string',
        'partOf' => 'string',
        'id' => 'string',
        'totalItems' => 'int',
        'orderedItems' => 'array',
        'next' => 'string',
    ])]
    private function getCollectionItems(Magazine $magazine, int $page): array
    {
        $subscriptions = $this->magazineSubscriptionRepository->findMagazineSubscribers(1, $magazine);
        $actors = array_map(fn ($sub) => $sub->user, iterator_to_array($subscriptions->getCurrentPageResults()));

        $items = [];
        foreach ($actors as $actor) {
            $items[] = $this->manager->getActorProfileId($actor);
        }

        return $this->collectionItemsWrapper->build(
            'ap_magazine_followers',
            ['name' => $magazine->name],
            $subscriptions,
            $items,
            $page
        );
    }
}
