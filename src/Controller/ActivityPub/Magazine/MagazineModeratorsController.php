<?php

declare(strict_types=1);

namespace App\Controller\ActivityPub\Magazine;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Magazine;
use App\Entity\Moderator;
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
        private readonly UrlGeneratorInterface $urlGenerator,
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
        '@context' => 'array',
        'type' => 'string',
        'id' => 'string',
        'totalItems' => 'int',
        'orderedItems' => 'array',
    ])]
    private function getCollectionItems(Magazine $magazine): array
    {
        $moderators = $this->magazineRepository->findModerators($magazine, perPage: $magazine->moderators->count());

        $items = [];
        foreach ($moderators->getCurrentPageResults() as /* @var Moderator $mod */ $mod) {
            $actor = $mod->user;
            $items[] = $this->manager->getActorProfileId($actor);
        }

        return [
            '@context' => [ActivityPubActivityInterface::CONTEXT_URL],
            'type' => 'OrderedCollection',
            'id' => $this->urlGenerator->generate('ap_magazine_moderators', ['name' => $magazine->name], UrlGeneratorInterface::ABSOLUTE_URL),
            'totalItems' => \sizeof($items),
            'orderedItems' => $items,
        ];
    }
}
