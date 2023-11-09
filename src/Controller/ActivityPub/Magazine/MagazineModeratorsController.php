<?php

declare(strict_types=1);

namespace App\Controller\ActivityPub\Magazine;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Magazine;
use App\Repository\MagazineRepository;
use App\Service\ActivityPubManager;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MagazineModeratorsController
{
    public function __construct(
        private readonly ActivityPubManager $manager,
        private readonly MagazineRepository $magazineRepository,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function __invoke(Magazine $magazine, Request $request): JsonResponse
    {
        $data = $this->getCollectionItems($magazine);
        $response = new JsonResponse($data);
        $response->headers->set('Content-Type', 'application/activity+json');

        return $response;
    }

    #[ArrayShape([
        '@context' => 'string',
        'type' => 'string',
        'id' => 'string',
        'totalItems' => 'int',
        'orderedItems' => 'array',
    ])]
    private function getCollectionItems(Magazine $magazine): array
    {
        $moderatorsCount = $this->magazineRepository->findModerators($magazine)->count();
        $moderators = $this->magazineRepository->findModerators($magazine, perPage: $moderatorsCount);
        $actors = array_map(fn ($mod) => $mod->user, iterator_to_array($moderators->getCurrentPageResults()));

        $items = [];
        foreach ($actors as $actor) {
            $items[] = $this->manager->getActorProfileId($actor);
        }

        $routeName = 'ap_magazine_moderators';
        $routeParams = ['name' => $magazine->name];

        return [
            '@context' => ActivityPubActivityInterface::CONTEXT_URL,
            'type' => 'OrderedCollection',
            'id' => $this->urlGenerator->generate($routeName, $routeParams, UrlGeneratorInterface::ABSOLUTE_URL),
            'totalItems' => $moderatorsCount,
            'orderedItems' => $items,
        ];
    }
}
