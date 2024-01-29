<?php

declare(strict_types=1);

namespace App\Controller\Entry;

use App\Controller\AbstractController;
use App\Entity\Magazine;
use App\Entity\User;
use App\PageView\EntryPageView;
use App\Pagination\Pagerfanta as MbinPagerfanta;
use App\Repository\Criteria;
use App\Repository\EntryRepository;
use Pagerfanta\PagerfantaInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class EntryFrontController extends AbstractController
{
    public function __construct(private readonly EntryRepository $repository)
    {
    }

    public function root(?string $sortBy, ?string $time, ?string $type, string $federation, Request $request): Response
    {
        return $this->front($sortBy, $time, $type, $request->query->get('filter'), $federation, $request);
    }

    public function front(?string $sortBy, ?string $time, ?string $type, string $filter, string $federation, Request $request): Response
    {
        $user = $this->getUser();

        if ('_default' === $filter) {
            $filter = $this->filterFor($user);
        }

        $criteria = new EntryPageView($this->getPageNb($request));
        $criteria->showSortOption($criteria->resolveSort($sortBy))
            ->setFederation($federation)
            ->setTime($criteria->resolveTime($time))
            ->setType($criteria->resolveType($type));

        if ('sub' === $filter) {
            $this->denyAccessUnlessGranted('ROLE_USER');
            $user = $this->getUserOrThrow();
            $criteria->subscribed = true;
        } elseif ('mod' === $filter) {
            $this->denyAccessUnlessGranted('ROLE_USER');
            $criteria->moderated = true;
        } elseif ('fav' === $filter) {
            $this->denyAccessUnlessGranted('ROLE_USER');
            $criteria->favourite = true;
        } elseif ($filter && 'all' !== $filter) {
            throw new LogicException('Invalid filter '.$filter);
        }

        if (null !== $user && 0 < \count($user->preferredLanguages)) {
            $criteria->languages = $user->preferredLanguages;
        }

        $method = $criteria->resolveSort($sortBy);
        $posts = $this->$method($criteria);

        $posts = $this->handleCrossposts($posts);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(
                [
                    'html' => $this->renderView(
                        'entry/_list.html.twig',
                        [
                            'entries' => $posts,
                        ]
                    ),
                ]
            );
        }

        return $this->render(
            'entry/front.html.twig',
            [
                'entries' => $posts,
                'criteria' => $criteria,
            ]
        );
    }

    public function front_redirect(?string $sortBy, ?string $time, ?string $type, string $federation, Request $request): Response
    {
        $user = $this->getUser(); // Fetch the user
        $filter = $this->filterFor($user); // Determine the filter based on the user

        return $this->redirectToRoute('front', [
            'filter' => $filter,
            'sortBy' => $sortBy,
            'time' => $time,
            'type' => $type,
            'federation' => $federation,
        ]);
    }

    public function magazine(
        #[MapEntity(expr: 'repository.findOneByName(name)')]
        Magazine $magazine,
        ?string $sortBy,
        ?string $time,
        ?string $type,
        string $federation,
        Request $request
    ): Response {
        $user = $this->getUser();
        $response = new Response();
        if ($magazine->apId) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        $criteria = (new EntryPageView($this->getPageNb($request)));
        $criteria->showSortOption($criteria->resolveSort($sortBy))
            ->setFederation($federation)
            ->setTime($criteria->resolveTime($time))
            ->setType($criteria->resolveType($type));

        $filter = $request->query->get('filter');

        if ('sub' === $filter) {
            $this->denyAccessUnlessGranted('ROLE_USER');
            $user = $this->getUserOrThrow();
            $criteria->subscribed = true;
        } elseif ('mod' === $filter) {
            $this->denyAccessUnlessGranted('ROLE_USER');
            $criteria->moderated = true;
        } elseif ('fav' === $filter) {
            $this->denyAccessUnlessGranted('ROLE_USER');
            $criteria->favourite = true;
        } elseif ($filter && 'all' !== $filter) {
            throw new LogicException('Invalid filter '.$filter);
        }

        $criteria->magazine = $magazine;
        $criteria->stickiesFirst = true;

        if (null !== $user && 0 < \count($user->preferredLanguages)) {
            $criteria->languages = $user->preferredLanguages;
        }

        $method = $criteria->resolveSort($sortBy);
        $listing = $this->$method($criteria);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(
                [
                    'html' => $this->renderView(
                        'entry/_list.html.twig',
                        [
                            'magazine' => $magazine,
                            'entries' => $listing,
                        ]
                    ),
                ]
            );
        }

        return $this->render(
            'entry/front.html.twig',
            [
                'magazine' => $magazine,
                'entries' => $listing,
                'criteria' => $criteria,
            ],
            $response
        );
    }

    private function filterFor(?User $user): string
    {
        if ($user) {
            return match ($user->homepage) {
                User::HOMEPAGE_SUB => 'sub',
                User::HOMEPAGE_MOD => 'mod',
                User::HOMEPAGE_FAV => 'fav',
                default => 'all',
            };
        } else {
            return 'all'; // Global default
        }
    }

    private function hot(EntryPageView $criteria): PagerfantaInterface
    {
        return $this->repository->findByCriteria($criteria->showSortOption(Criteria::SORT_HOT));
    }

    private function top(EntryPageView $criteria): PagerfantaInterface
    {
        return $this->repository->findByCriteria($criteria->showSortOption(Criteria::SORT_TOP));
    }

    private function active(EntryPageView $criteria): PagerfantaInterface
    {
        return $this->repository->findByCriteria($criteria->showSortOption(Criteria::SORT_ACTIVE));
    }

    private function newest(EntryPageView $criteria): PagerfantaInterface
    {
        return $this->repository->findByCriteria($criteria->showSortOption(Criteria::SORT_NEW));
    }

    private function oldest(EntryPageView $criteria): PagerfantaInterface
    {
        return $this->repository->findByCriteria($criteria->showSortOption(Criteria::SORT_OLD));
    }

    private function commented(EntryPageView $criteria): PagerfantaInterface
    {
        return $this->repository->findByCriteria($criteria->showSortOption(Criteria::SORT_COMMENTED));
    }

    private function handleCrossposts($pagination): PagerfantaInterface
    {
        $posts = $pagination->getCurrentPageResults();

        $firstIndexes = [];
        $tmp = [];
        $duplicates = [];

        foreach ($posts as $post) {
            $groupingField = !empty($post->url) ? $post->url : $post->title;

            if (!\in_array($groupingField, $firstIndexes)) {
                $tmp[] = $post;
                $firstIndexes[] = $groupingField;
            } else {
                if (!\in_array($groupingField, array_column($duplicates, 'groupingField'), true)) {
                    $duplicates[] = (object) [
                        'groupingField' => $groupingField,
                        'items' => [],
                    ];
                }

                $duplicateIndex = array_search($groupingField, array_column($duplicates, 'groupingField'));
                $duplicates[$duplicateIndex]->items[] = $post;

                $post->cross = true;
            }
        }

        $results = [];
        foreach ($tmp as $item) {
            $results[] = $item;
            $groupingField = !empty($item->url) ? $item->url : $item->title;

            $duplicateIndex = array_search($groupingField, array_column($duplicates, 'groupingField'));
            if (false !== $duplicateIndex) {
                foreach ($duplicates[$duplicateIndex]->items as $duplicateItem) {
                    $results[] = $duplicateItem;
                }
            }
        }

        $pagerfanta = new MbinPagerfanta($pagination->getAdapter());
        $pagerfanta->setCurrentPage($pagination->getCurrentPage());
        $pagerfanta->setMaxNbPages($pagination->getNbPages());
        $pagerfanta->setCurrentPageResults($results);

        return $pagerfanta;
    }
}
