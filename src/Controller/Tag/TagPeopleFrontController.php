<?php

declare(strict_types=1);

namespace App\Controller\Tag;

use App\Controller\AbstractController;
use App\Repository\MagazineRepository;
use App\Repository\PostRepository;
use App\Repository\TagRepository;
use App\Service\PeopleManager;
use App\Service\TagExtractor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TagPeopleFrontController extends AbstractController
{
    public function __construct(
        private readonly PeopleManager $manager,
        private readonly TagExtractor $tagManager,
        private readonly TagRepository $tagRepository,
        private readonly MagazineRepository $magazineRepository,
    ) {
    }

    public function __invoke(
        string $name,
        ?string $sortBy,
        ?string $time,
        PostRepository $repository,
        Request $request,
    ): Response {
        $tag = $this->tagManager->transliterate(strtolower($name));
        $hashtag = $this->tagRepository->findOneBy(['tag' => $tag]);

        $magazines = array_filter(
            $this->magazineRepository->findByActivity(),
            fn ($val) => 'random' !== $val->name
        );
        $localPeople = $this->manager->general();
        $generalPeople = $this->manager->general(true);
        $counts = $this->tagRepository->getCounts($tag);

        return $this->render(
            'tag/people.html.twig', [
                'hashtag' => $hashtag,
                'tag' => $name,
                'magazines' => $magazines,
                'local' => $localPeople,
                'federated' => $generalPeople,
                'counts' => $counts,
            ]
        );
    }
}
