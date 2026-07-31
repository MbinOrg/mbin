<?php

declare(strict_types=1);

namespace App\Controller\Tag;

use App\Controller\AbstractController;
use App\Repository\TagRepository;
use App\Service\SubjectOverviewManager;
use App\Service\TagExtractor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TagOverviewController extends AbstractController
{
    public function __construct(
        private readonly TagExtractor $tagManager,
        private readonly TagRepository $tagRepository,
        private readonly SubjectOverviewManager $overviewManager,
    ) {
    }

    public function __invoke(string $name, Request $request): Response
    {
        $tag = $this->tagManager->transliterate(strtolower($name));

        $hashtag = $this->tagRepository->findOneBy(['tag' => $tag]);

        $activity = $this->tagRepository->findOverall(
            $this->getPageNb($request),
            $tag
        );

        $params = [
            'hashtag' => $hashtag,
            'tag' => $name,
            'results' => $this->overviewManager->buildList($activity),
            'pagination' => $activity,
            'counts' => $this->tagRepository->getCounts($name),
        ];

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['html' => $this->renderView('tag/_list.html.twig', $params)]);
        }

        return $this->render('tag/overview.html.twig', $params);
    }
}
