<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\SearchDto;
use App\Form\HashtagSearchType;
use App\Form\MagazinePageViewType;
use App\Form\SearchType;
use App\PageView\HashtagSearchView;
use App\PageView\MagazinePageView;
use App\Repository\Criteria;
use App\Repository\MagazineRepository;
use App\Repository\TagRepository;
use App\Service\SearchManager;
use App\Service\SettingsManager;
use App\Service\TagManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchController extends AbstractController
{
    public function __construct(
        private readonly SearchManager $manager,
        private readonly MagazineRepository $magazineRepository,
        private readonly TagManager $tagManager,
        private readonly TagRepository $tagRepository,
        private readonly SettingsManager $settingsManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function redirectFromOldRoute(Request $request): Response
    {
        return $this->redirectToRoute('search_general', status: Response::HTTP_MOVED_PERMANENTLY);
    }

    public function general(Request $request): Response
    {
        $dto = new SearchDto();
        $dto->since = new \DateTimeImmutable('@0');
        $form = $this->createForm(SearchType::class, $dto, ['csrf_protection' => false]);
        try {
            $form = $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                /** @var SearchDto $dto */
                $dto = $form->getData();
                $query = trim($dto->q);
                $this->logger->debug('searching for {query}', ['query' => $query]);

                $objects = [];
                if ($this->federatedSearchAllowed() && (str_contains($query, '@') || false !== filter_var($query, FILTER_VALIDATE_URL))) {
                    $this->logger->debug('searching for a matched handle or ap url {query}', ['query' => $query]);
                    $objects = $this->findObjectsByAp($query);
                }

                $user = $this->getUser();
                $res = $this->manager->findPaginated($user, $query, $this->getPageNb($request), authorId: $dto->user?->getId(), magazineId: $dto->magazine?->getId(), specificType: $dto->type, sinceDate: $dto->since);

                $this->logger->debug('results: {num}', ['num' => $res->count()]);

                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'html' => $this->renderView('search/_list.html.twig', [
                            'results' => $res,
                        ]),
                    ]);
                }

                return $this->render(
                    'search/front.html.twig',
                    [
                        'objects' => $objects,
                        'results' => $res,
                        'pagination' => $res,
                        'form' => $form->createView(),
                        'q' => $query,
                    ]
                );
            }
        } catch (\Exception $e) {
            $this->logger->error($e);
        }

        return $this->render(
            'search/front.html.twig',
            [
                'form' => $form->createView(),
                'objects' => [],
                'results' => [],
            ]
        );
    }

    public function magazines(Request $request): Response
    {
        $user = $this->getUser();

        $criteria = new MagazinePageView(
            $this->getPageNb($request),
            Criteria::SORT_ACTIVE,
            Criteria::AP_ALL,
            $user?->hideAdult ? MagazinePageView::ADULT_HIDE : MagazinePageView::ADULT_SHOW,
        );

        $form = $this->createForm(MagazinePageViewType::class, $criteria);

        $form->handleRequest($request);

        if (null !== $criteria->query) {
            $magazines = $this->magazineRepository->findPaginated($criteria);
        } else {
            $magazines = [];
        }

        return $this->render(
            'search/front.html.twig',
            [
                'form' => $form->createView(),
                'magazines' => $magazines,
                'criteria' => $criteria,
                'view' => 'list',
            ]
        );
    }

    public function hashtags(Request $request): Response
    {
        $criteria = new HashtagSearchView(
            $this->getPageNb($request),
        );

        $form = $this->createForm(HashtagSearchType::class, $criteria);

        $form->handleRequest($request);

        if (null !== $criteria->query) {
            $hashtags = $this->tagManager->searchWithCriteria($criteria);
        } else {
            $hashtags = $this->tagRepository->findAllPaginated($criteria->page);
        }

        return $this->render(
            'search/front.html.twig',
            [
                'form' => $form->createView(),
                'hashtags' => $hashtags,
                'criteria' => $criteria,
            ]
        );
    }

    private function federatedSearchAllowed(): bool
    {
        return !$this->settingsManager->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN')
            || $this->getUser();
    }

    private function findObjectsByAp(string $urlOrHandle): array
    {
        $result = $this->manager->findActivityPubActorsOrObjects($urlOrHandle);

        foreach ($result['errors'] as $error) {
            /** @var \Throwable $error */
            $this->addFlash('error', $error->getMessage());
        }

        return $result['results'];
    }
}
