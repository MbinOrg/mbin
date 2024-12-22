<?php

declare(strict_types=1);

namespace App\Controller\Tag;

use App\Controller\AbstractController;
use App\PageView\EntryCommentPageView;
use App\Repository\EntryCommentRepository;
use App\Repository\TagRepository;
use App\Service\TagExtractor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TagCommentFrontController extends AbstractController
{
    public function __construct(
        private readonly EntryCommentRepository $repository,
        private readonly TagRepository $tagRepository,
        private readonly TagExtractor $tagManager,
    ) {
    }

    public function __invoke(string $name, ?string $sortBy, ?string $time, Request $request): Response
    {
        $criteria = new EntryCommentPageView($this->getPageNb($request));
        $criteria->showSortOption($criteria->resolveSort($sortBy))
            ->setTime($criteria->resolveTime($time))
            ->setTag($this->tagManager->transliterate(strtolower($name)));

        $params = [
            'comments' => $this->repository->findByCriteria($criteria),
            'tag' => $name,
            'counts' => $this->tagRepository->getCounts($name),
        ];

        return $this->render(
            'tag/comments.html.twig',
            $params
        );
    }
}
