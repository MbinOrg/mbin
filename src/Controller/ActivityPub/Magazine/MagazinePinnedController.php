<?php

declare(strict_types=1);

namespace App\Controller\ActivityPub\Magazine;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Magazine;
use App\Factory\ActivityPub\EntryPageFactory;
use App\Repository\EntryRepository;
use App\Repository\TagLinkRepository;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MagazinePinnedController
{
    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EntryPageFactory $entryFactory,
        private readonly TagLinkRepository $tagLinkRepository,
    ) {
    }

    public function __invoke(Magazine $magazine, Request $request): JsonResponse
    {
        $data = $this->getCollectionItems($magazine);
        $response = new JsonResponse($data);
        $response->headers->set('Content-Type', 'application/activity+json');

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    #[ArrayShape([
        '@context' => 'array',
        'type' => 'string',
        'id' => 'string',
        'totalItems' => 'int',
        'orderedItems' => 'array',
    ])]
    private function getCollectionItems(Magazine $magazine): array
    {
        $pinned = $this->entryRepository->findPinned($magazine);

        $items = [];
        foreach ($pinned as $entry) {
            $items[] = $this->entryFactory->create($entry, $this->tagLinkRepository->getTagsOfEntry($entry));
        }

        return [
            '@context' => [ActivityPubActivityInterface::CONTEXT_URL],
            'type' => 'OrderedCollection',
            'id' => $this->urlGenerator->generate('ap_magazine_pinned', ['name' => $magazine->name], UrlGeneratorInterface::ABSOLUTE_URL),
            'totalItems' => \sizeof($items),
            'orderedItems' => $items,
        ];
    }
}
