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

        $criteria = $this->createCriteria($content, $request);
        $criteria->showSortOption($criteria->resolveSort($sortBy))
            ->setFederation($federation)
            ->setTime($criteria->resolveTime($time))
            ->setType($criteria->resolveType($type));

        if ('def' === $subscription) {
            $subscription = $this->subscriptionFor($user);
        }
        $this->handleSubscription($subscription, $user, $criteria);

        $this->setUserPreferences($user, $criteria);

        $entities = ('threads' === $content) ? $this->entryRepository->findByCriteria($criteria) : $this->postRepository->findByCriteria($criteria);
        if ('threads' === $content) {
            $entities = $this->handleCrossposts($entities);
        }

        $templatePath = ('threads' === $content) ? 'entry/' : 'post/';
        $dataKey = ('threads' === $content) ? 'entries' : 'posts';

        return $this->renderResponse($request, $content, $criteria, [$dataKey => $entities], $templatePath);
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

        $criteria = $this->createCriteria($content, $request);
        $criteria->showSortOption($criteria->resolveSort($sortBy))
            ->setFederation($federation)
            ->setTime($criteria->resolveTime($time))
            ->setType($criteria->resolveType($type));
        $criteria->magazine = $magazine;
        $criteria->stickiesFirst = true;

        $subscription = $request->query->get('subscription');
        if (!$subscription) {
            $subscription = 'all';
        }
        $this->handleSubscription($subscription, $user, $criteria);

        $this->setUserPreferences($user, $criteria);

        $entities = ('threads' === $content) ? $this->entryRepository->findByCriteria($criteria) : $this->postRepository->findByCriteria($criteria);
        // Note no crosspost handling

        $templatePath = ('threads' === $content) ? 'entry/' : 'post/';
        $dataKey = ('threads' === $content) ? 'entries' : 'posts';

        return $this->renderResponse($request, $content, $criteria, [$dataKey => $entities, 'magazine' => $magazine], $templatePath);
    }

    private function createCriteria(string $content, Request $request)
    {
        if ('threads' === $content) {
            $criteria = new EntryPageView($this->getPageNb($request));
        } elseif ('microblog' === $content) {
            $criteria = new PostPageView($this->getPageNb($request));
        } else {
            throw new \LogicException('Invalid content '.$content);
        }

        return $criteria->setContent($content);
    }

    private function handleSubscription(string $subscription, $user, &$criteria)
    {
        if ('sub' === $subscription) {
            $this->denyAccessUnlessGranted('ROLE_USER');
            $this->getUserOrThrow();
            $criteria->subscribed = true;
        } elseif ('mod' === $subscription) {
            $this->denyAccessUnlessGranted('ROLE_USER');
            $this->getUserOrThrow();
            $criteria->moderated = true;
        } elseif ('fav' === $subscription) {
            $this->denyAccessUnlessGranted('ROLE_USER');
            $this->getUserOrThrow();
            $criteria->favourite = true;
        } elseif ($subscription && 'all' !== $subscription) {
            throw new \LogicException('Invalid subscription filter '.$subscription);
        }
    }

    private function setUserPreferences(?User $user, &$criteria)
    {
        if (null !== $user && 0 < \count($user->preferredLanguages)) {
            $criteria->languages = $user->preferredLanguages;
        }
    }

    private function renderResponse(Request $request, $content, $criteria, $data, $templatePath)
    {
        $baseData = ['criteria' => $criteria] + $data;
        if ('microblog' === $content) {
            $baseData['form'] = $this->createForm(PostType::class)->setData(new PostDto())->createView();
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'html' => $this->renderView($templatePath.'_list.html.twig', $data),
            ]);
        }

        return $this->render($templatePath.'front.html.twig', $baseData);
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
