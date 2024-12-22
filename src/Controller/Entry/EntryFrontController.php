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
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

class EntryFrontController extends AbstractController
{
    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly PostRepository $postRepository,
    ) {
    }

    public function front(
        string $subscription,
        string $content,
        ?string $sortBy,
        ?string $time,
        string $federation,
        #[MapQueryParameter]
        ?string $type,
        Request $request,
    ): Response {
        $user = $this->getUser();

        $criteria = $this->createCriteria($content, $request);
        $criteria->showSortOption($criteria->resolveSort($sortBy))
            ->setFederation($federation)
            ->setTime($criteria->resolveTime($time))
            ->setType($criteria->resolveType($type));

        if ('home' === $subscription) {
            $subscription = $this->subscriptionFor($user);
        }
        $this->handleSubscription($subscription, $criteria);

        $this->setUserPreferences($user, $criteria);

        if ('threads' === $content) {
            $entities = $this->entryRepository->findByCriteria($criteria);
            $entities = $this->handleCrossposts($entities);
            $templatePath = 'entry/';
            $dataKey = 'entries';
        } elseif ('microblog' === $content) {
            $entities = $this->postRepository->findByCriteria($criteria);
            $templatePath = 'post/';
            $dataKey = 'posts';
        } else {
            throw new \LogicException("Invalid content filter '{$content}'");
        }

        return $this->renderResponse(
            $request,
            $content,
            $criteria,
            [$dataKey => $entities],
            $templatePath,
            $user
        );
    }

    public function frontRedirect(
        string $content,
        ?string $sortBy,
        ?string $time,
        string $federation,
        #[MapQueryParameter]
        ?string $type,
        Request $request,
    ): Response {
        $user = $this->getUser();
        $subscription = $this->subscriptionFor($user);

        return $this->redirectToRoute('front', [
            'subscription' => $subscription,
            'sortBy' => $sortBy,
            'time' => $time,
            'type' => $type,
            'federation' => $federation,
            'content' => $content,
        ]);
    }

    public function magazine(
        #[MapEntity(expr: 'repository.findOneByName(name)')]
        Magazine $magazine,
        string $content,
        ?string $sortBy,
        ?string $time,
        string $federation,
        #[MapQueryParameter]
        ?string $type,
        Request $request,
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

        $subscription = $request->query->get('subscription') ?: 'all';
        $this->handleSubscription($subscription, $criteria);

        $this->setUserPreferences($user, $criteria);

        if ('threads' === $content) {
            $entities = $this->entryRepository->findByCriteria($criteria);
            // Note no crosspost handling
            $templatePath = 'entry/';
            $dataKey = 'entries';
        } elseif ('microblog' === $content) {
            $entities = $this->postRepository->findByCriteria($criteria);
            $templatePath = 'post/';
            $dataKey = 'posts';
        } else {
            throw new \LogicException("Invalid content filter '{$content}'");
        }

        return $this->renderResponse(
            $request,
            $content,
            $criteria,
            [$dataKey => $entities, 'magazine' => $magazine],
            $templatePath,
            $user
        );
    }

    /**
     * @param string $name magazine name
     */
    public function magazineRedirect(
        string $name,
        string $content,
        ?string $sortBy,
        ?string $time,
        string $federation,
        #[MapQueryParameter]
        ?string $type,
        Request $request,
    ): Response {
        $user = $this->getUser(); // Fetch the user
        $subscription = $this->subscriptionFor($user); // Determine the subscription filter based on the user

        return $this->redirectToRoute('front_magazine', [
            'name' => $name,
            'subscription' => $subscription,
            'sortBy' => $sortBy,
            'time' => $time,
            'type' => $type,
            'federation' => $federation,
            'content' => $content,
        ]);
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

    private function handleSubscription(string $subscription, &$criteria)
    {
        if (\in_array($subscription, ['sub', 'mod', 'fav'])) {
            $this->denyAccessUnlessGranted('ROLE_USER');
            $this->getUserOrThrow();
        }

        if ('sub' === $subscription) {
            $criteria->subscribed = true;
        } elseif ('mod' === $subscription) {
            $criteria->moderated = true;
        } elseif ('fav' === $subscription) {
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

    private function renderResponse(Request $request, $content, $criteria, $data, $templatePath, ?User $user)
    {
        $baseData = array_merge(['criteria' => $criteria], $data);

        if ('microblog' === $content) {
            $dto = new PostDto();
            if (isset($data['magazine'])) {
                $dto->magazine = $data['magazine'];
            }
            $baseData['form'] = $this->createForm(PostType::class)->setData($dto)->createView();
            $baseData['user'] = $user;
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

            if (!\in_array($groupingField, $firstIndexes) || (empty($post->url) && \strlen($post->title) <= 10)) {
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
