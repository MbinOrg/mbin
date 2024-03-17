<?php

declare(strict_types=1);

namespace App\Controller\Entry;

use App\Controller\AbstractController;
use App\DTO\PostDto;
use App\Entity\Magazine;
use App\Entity\User;
use App\Form\PostType;
use App\PageView\EntryPageView;
use App\PageView\PostPageView;
use App\Pagination\Pagerfanta as MbinPagerfanta;
use App\Repository\EntryRepository;
use App\Repository\PostRepository;
use Pagerfanta\PagerfantaInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class EntryFrontController extends AbstractController
{
    public function __construct(private readonly EntryRepository $entryRepository, private readonly PostRepository $postRepository)
    {
    }

    public function front(?string $sortBy, ?string $time, ?string $type, string $subscription, string $federation, string $content, Request $request): Response
    {
        $user = $this->getUser();

        if ('def' === $subscription) {
            $subscription = $this->subscriptionFor($user);
        }

        if ('threads' === $content) {
            $criteria = new EntryPageView($this->getPageNb($request));
            $criteria->setContent('threads');
        } elseif ('microblog' === $content) {
            $criteria = new PostPageView($this->getPageNb($request));
            $criteria->setContent('microblog');
        } else {
            throw new \LogicException('Invalid content '.$content);
        }

        $criteria->showSortOption($criteria->resolveSort($sortBy))
            ->setFederation($federation)
            ->setTime($criteria->resolveTime($time))
            ->setType($criteria->resolveType($type));

        if ('sub' === $subscription) {
            $this->denyAccessUnlessGranted('ROLE_USER');
            $user = $this->getUserOrThrow();
            $criteria->subscribed = true;
        } elseif ('mod' === $subscription) {
            $this->denyAccessUnlessGranted('ROLE_USER');
            $user = $this->getUserOrThrow();
            $criteria->moderated = true;
        } elseif ('fav' === $subscription) {
            $this->denyAccessUnlessGranted('ROLE_USER');
            $user = $this->getUserOrThrow();
            $criteria->favourite = true;
        } elseif ($subscription && 'all' !== $subscription) {
            throw new \LogicException('Invalid subscription filter '.$subscription);
        }

        if (null !== $user && 0 < \count($user->preferredLanguages)) {
            $criteria->languages = $user->preferredLanguages;
        }

        if ('threads' === $content) {
            $entries = $this->entryRepository->findByCriteria($criteria);
            $entries = $this->handleCrossposts($entries);

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(
                    [
                        'html' => $this->renderView(
                            'entry/_list.html.twig',
                            [
                                'entries' => $entries,
                            ]
                        ),
                    ]
                );
            }

            return $this->render(
                'entry/front.html.twig',
                [
                    'entries' => $entries,
                    'criteria' => $criteria,
                ]
            );
        } elseif ('microblog' === $content) {
            $posts = $this->postRepository->findByCriteria($criteria);

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(
                    [
                        'html' => $this->renderView(
                            'post/_list.html.twig',
                            [
                                'posts' => $posts,
                            ]
                        ),
                    ]
                );
            }

            return $this->render(
                'post/front.html.twig',
                [
                    'posts' => $posts,
                    'criteria' => $criteria,
                    'form' => $this->createForm(PostType::class)->setData(new PostDto())->createView(),
                ]
            );
        } else {
            throw new \LogicException('Invalid content filter '.$content);
        }
    }

    // $name is magazine name, for compatibility
    public function front_redirect(?string $sortBy, ?string $time, ?string $type, string $federation, string $content, ?string $name, Request $request): Response
    {
        $user = $this->getUser(); // Fetch the user
        $subscription = $this->subscriptionFor($user); // Determine the subscription filter based on the user

        if ($name) {
            return $this->redirectToRoute('front_magazine', [
                'name' => $name,
                'subscription' => $subscription,
                'sortBy' => $sortBy,
                'time' => $time,
                'type' => $type,
                'federation' => $federation,
                'content' => $content,
            ]);
        } else {
            return $this->redirectToRoute('front', [
                'subscription' => $subscription,
                'sortBy' => $sortBy,
                'time' => $time,
                'type' => $type,
                'federation' => $federation,
                'content' => $content,
            ]);
        }
    }

    public function magazine(
        #[MapEntity(expr: 'repository.findOneByName(name)')]
        Magazine $magazine,
        ?string $sortBy,
        ?string $time,
        ?string $type,
        string $federation,
        string $content,
        Request $request
    ): Response {
        $user = $this->getUser();
        $response = new Response();
        if ($magazine->apId) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if ('threads' === $content) {
            $criteria = new EntryPageView($this->getPageNb($request));
            $criteria->setContent('threads');
        } elseif ('microblog' === $content) {
            $criteria = new PostPageView($this->getPageNb($request));
            $criteria->setContent('microblog');
        } else {
            throw new \LogicException('Invalid content '.$content);
        }

        $criteria->showSortOption($criteria->resolveSort($sortBy))
            ->setFederation($federation)
            ->setTime($criteria->resolveTime($time))
            ->setType($criteria->resolveType($type));

        $subscription = $request->query->get('subscription');

        if ('sub' === $subscription) {
            $this->denyAccessUnlessGranted('ROLE_USER');
            $user = $this->getUserOrThrow();
            $criteria->subscribed = true;
        } elseif ('mod' === $subscription) {
            $this->denyAccessUnlessGranted('ROLE_USER');
            $user = $this->getUserOrThrow();
            $criteria->moderated = true;
        } elseif ('fav' === $subscription) {
            $this->denyAccessUnlessGranted('ROLE_USER');
            $user = $this->getUserOrThrow();
            $criteria->favourite = true;
        } elseif ($subscription && 'all' !== $subscription) {
            throw new \LogicException('Invalid subscription filter '.$subscription);
        }

        $criteria->magazine = $magazine;
        $criteria->stickiesFirst = true;

        if (null !== $user && 0 < \count($user->preferredLanguages)) {
            $criteria->languages = $user->preferredLanguages;
        }

        if ('threads' === $content) {
            $listing = $this->entryRepository->findByCriteria($criteria);

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
                ]
            );
        } elseif ('microblog' === $content) {
            $listing = $this->postRepository->findByCriteria($criteria);

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(
                    [
                        'html' => $this->renderView(
                            'post/_list.html.twig',
                            [
                                'posts' => $posts,
                            ]
                        ),
                    ]
                );
            }

            return $this->render(
                'post/front.html.twig',
                [
                    'magazine' => $magazine,
                    'posts' => $listing,
                    'criteria' => $criteria,
                    'form' => $this->createForm(PostType::class)->setData(new PostDto())->createView(),
                ]
            );
        } else {
            throw new \LogicException('Invalid content filter '.$content);
        }
    }

    private function subscriptionFor(?User $user): string
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
