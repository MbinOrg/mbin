<?php

declare(strict_types=1);

namespace App\Controller\Tag;

use App\Controller\AbstractController;
use App\PageView\PostPageView;
use App\Repository\PostRepository;
use App\Repository\TagRepository;
use App\Service\TagExtractor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TagPostFrontController extends AbstractController
{
    public function __construct(
        private readonly TagExtractor $tagManager,
        private readonly TagRepository $tagRepository,
        private readonly Security $security,
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

        $criteria = new PostPageView($this->getPageNb($request), $this->security);
        $criteria->showSortOption($criteria->resolveSort($sortBy))
            ->setTime($criteria->resolveTime($time))
            ->setTag($tag);

        $posts = $repository->findByCriteria($criteria);

        $hashtag = $this->tagRepository->findOneBy(['tag' => $tag]);

        return $this->render(
            'tag/posts.html.twig',
            [
                'hashtag' => $hashtag,
                'tag' => $name,
                'posts' => $posts,
                'counts' => $this->tagRepository->getCounts($name),
            ]
        );
    }
}
