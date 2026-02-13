<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Enums\EApplicationStatus;
use App\PageView\EntryCommentPageView;
use App\PageView\EntryPageView;
use App\PageView\MagazinePageView;
use App\PageView\PostCommentPageView;
use App\PageView\PostPageView;
use App\Repository\Criteria;
use App\Repository\EntryCommentRepository;
use App\Repository\EntryRepository;
use App\Repository\MagazineRepository;
use App\Repository\NotificationRepository;
use App\Repository\PostCommentRepository;
use App\Repository\PostRepository;
use App\Repository\SearchRepository;
use App\Repository\UserRepository;
use App\Service\SubjectOverviewManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class UserFrontController extends AbstractController
{
    public function __construct(
        private readonly SubjectOverviewManager $overviewManager,
        private readonly NotificationRepository $notificationRepository,
        private readonly Security $security,
    ) {
    }

    public function front(
        #[MapEntity]
        User $user,
        Request $request,
        UserRepository $repository,
        SearchRepository $repository
    ): Response {
        $response = new Response();
        if ($user->apId) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        $requestedByUser = $this->getUser();
        $hideAdult = (!$requestedByUser || $requestedByUser->hideAdult);

        if (EApplicationStatus::Approved !== $user->getApplicationStatus()) {
            throw $this->createNotFoundException();
        }

        if ($user->isDeleted && (!$requestedByUser || (!$requestedByUser->isAdmin() && !$requestedByUser->isModerator()) || null === $user->markedForDeletionAt)) {
            throw $this->createNotFoundException();
        }

        if ($loggedInUser = $this->getUser()) {
            $this->notificationRepository->markUserSignupNotificationsAsRead($loggedInUser, $user);
        }

        $activity = $repository->findUserPublicActivity($this->getPageNb($request), $user, $hideAdult);
        $results = $this->overviewManager->buildList($activity);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'html' => $this->renderView(
                    'layout/_generic_subject_list.html.twig',
                    [
                        'results' => $results,
                        'pagination' => $activity,
                    ]
                ),
            ]);
        }

        return $this->render(
            'user/overview.html.twig',
            [
                'user' => $user,
                'results' => $results,
                'pagination' => $activity,
            ],
            $response
        );
    }

    public function entries(
        #[MapEntity]
        User $user,
        Request $request,
        EntryRepository $repository,
    ): Response {
        $response = new Response();
        if ($user->apId) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        $requestedByUser = $this->getUser();
        if ($user->isDeleted && (!$requestedByUser || (!$requestedByUser->isAdmin() && !$requestedByUser->isModerator()) || null === $user->markedForDeletionAt)) {
            throw $this->createNotFoundException();
        }

        $criteria = new EntryPageView($this->getPageNb($request), $this->security);
        $criteria->sortOption = Criteria::SORT_NEW;
        $criteria->user = $user;
        $entries = $repository->findByCriteria($criteria);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'html' => $this->renderView(
                    'entry/_list.html.twig',
                    [
                        'entries' => $entries,
                    ]
                ),
            ]);
        }

        return $this->render(
            'user/entries.html.twig',
            [
                'user' => $user,
                'entries' => $entries,
            ],
            $response
        );
    }

    public function comments(
        #[MapEntity]
        User $user,
        Request $request,
        EntryCommentRepository $repository,
    ): Response {
        $response = new Response();
        if ($user->apId) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        $requestedByUser = $this->getUser();
        if ($user->isDeleted && (!$requestedByUser || (!$requestedByUser->isAdmin() && !$requestedByUser->isModerator()) || null === $user->markedForDeletionAt)) {
            throw $this->createNotFoundException();
        }

        $criteria = new EntryCommentPageView($this->getPageNb($request), $this->security);
        $criteria->sortOption = Criteria::SORT_NEW;
        $criteria->user = $user;
        $criteria->onlyParents = false;

        $comments = $repository->findByCriteria($criteria);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'html' => $this->renderView(
                    'entry/comment/_list.html.twig',
                    [
                        'comments' => $comments,
                        'criteria' => $criteria,
                        'showNested' => false,
                    ]
                ),
            ]);
        }

        return $this->render(
            'user/comments.html.twig',
            [
                'user' => $user,
                'comments' => $comments,
                'criteria' => $criteria,
            ],
            $response
        );
    }

    public function posts(
        #[MapEntity]
        User $user,
        Request $request,
        PostRepository $repository,
    ): Response {
        $response = new Response();
        if ($user->apId) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        $requestedByUser = $this->getUser();
        if ($user->isDeleted && (!$requestedByUser || (!$requestedByUser->isAdmin() && !$requestedByUser->isModerator()) || null === $user->markedForDeletionAt)) {
            throw $this->createNotFoundException();
        }
        $criteria = new PostPageView($this->getPageNb($request), $this->security);
        $criteria->sortOption = Criteria::SORT_NEW;
        $criteria->user = $user;

        $posts = $repository->findByCriteria($criteria);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'html' => $this->renderView(
                    'post/_list.html.twig',
                    [
                        'posts' => $posts,
                    ]
                ),
            ]);
        }

        return $this->render(
            'user/posts.html.twig',
            [
                'user' => $user,
                'posts' => $posts,
            ],
            $response
        );
    }

    public function replies(
        #[MapEntity]
        User $user,
        Request $request,
        PostCommentRepository $repository,
    ): Response {
        $response = new Response();
        if ($user->apId) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        $requestedByUser = $this->getUser();
        if ($user->isDeleted && (!$requestedByUser || (!$requestedByUser->isAdmin() && !$requestedByUser->isModerator()) || null === $user->markedForDeletionAt)) {
            throw $this->createNotFoundException();
        }

        $criteria = new PostCommentPageView($this->getPageNb($request), $this->security);
        $criteria->sortOption = Criteria::SORT_NEW;
        $criteria->onlyParents = false;
        $criteria->user = $user;

        $comments = $repository->findByCriteria($criteria);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'html' => $this->renderView(
                    'layout/_subject_list.html.twig',
                    [
                        'results' => $comments,
                        'criteria' => $criteria,
                        'postCommentAttributes' => [
                            'showNested' => false,
                            'withPost' => true,
                        ],
                    ]
                ),
            ]);
        }

        return $this->render(
            'user/replies.html.twig',
            [
                'user' => $user,
                'results' => $comments,
                'criteria' => $criteria,
            ],
            $response
        );
    }

    public function moderated(
        #[MapEntity]
        User $user,
        MagazineRepository $repository,
        Request $request,
    ): Response {
        $requestedByUser = $this->getUser();
        if ($user->isDeleted && (!$requestedByUser || (!$requestedByUser->isAdmin() && !$requestedByUser->isModerator()) || null === $user->markedForDeletionAt)) {
            throw $this->createNotFoundException();
        }
        $criteria = new MagazinePageView(
            $this->getPageNb($request),
            Criteria::SORT_ACTIVE,
            Criteria::AP_ALL,
            MagazinePageView::ADULT_SHOW,
        );

        $response = new Response();
        if ($user->apId) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $this->render(
            'user/moderated.html.twig',
            [
                'view' => 'list',
                'user' => $user,
                'magazines' => $repository->findModeratedMagazines($user, (int) $request->get('p', 1)),
                'criteria' => $criteria,
            ],
            $response
        );
    }

    public function subscriptions(
        #[MapEntity]
        User $user,
        MagazineRepository $repository,
        Request $request,
    ): Response {
        $requestedByUser = $this->getUser();
        if ($user->isDeleted && (!$requestedByUser || (!$requestedByUser->isAdmin() && !$requestedByUser->isModerator()) || null === $user->markedForDeletionAt)) {
            throw $this->createNotFoundException();
        }

        $response = new Response();
        if ($user->apId) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if (!$user->showProfileSubscriptions) {
            if ($user !== $this->getUser()) {
                throw new AccessDeniedException();
            }
        }

        return $this->render(
            'user/subscriptions.html.twig',
            [
                'user' => $user,
                'magazines' => $repository->findSubscribedMagazines($this->getPageNb($request), $user),
            ],
            $response
        );
    }

    public function followers(
        #[MapEntity]
        User $user,
        UserRepository $repository,
        Request $request,
    ): Response {
        $requestedByUser = $this->getUser();
        if ($user->isDeleted && (!$requestedByUser || (!$requestedByUser->isAdmin() && !$requestedByUser->isModerator()) || null === $user->markedForDeletionAt)) {
            throw $this->createNotFoundException();
        }

        $response = new Response();
        if ($user->apId) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $this->render(
            'user/followers.html.twig',
            [
                'user' => $user,
                'users' => $repository->findFollowers($this->getPageNb($request), $user),
            ],
            $response
        );
    }

    public function following(
        #[MapEntity]
        User $user,
        UserRepository $manager,
        Request $request,
    ): Response {
        $requestedByUser = $this->getUser();
        if ($user->isDeleted && (!$requestedByUser || (!$requestedByUser->isAdmin() && !$requestedByUser->isModerator()) || null === $user->markedForDeletionAt)) {
            throw $this->createNotFoundException();
        }

        $response = new Response();
        if ($user->apId) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if (!$user->showProfileFollowings && !$user->apId) {
            if ($user !== $this->getUser()) {
                throw new AccessDeniedException();
            }
        }

        return $this->render(
            'user/following.html.twig',
            [
                'user' => $user,
                'users' => $manager->findFollowing($this->getPageNb($request), $user),
            ],
            $response
        );
    }

    public function boosts(
        #[MapEntity]
        User $user,
        Request $request,
        SearchRepository $repository,
    ): Response {
        $requestedByUser = $this->getUser();
        if ($user->isDeleted && (!$requestedByUser || (!$requestedByUser->isAdmin() && !$requestedByUser->isModerator()) || null === $user->markedForDeletionAt)) {
            throw $this->createNotFoundException();
        }

        $response = new Response();
        if ($user->apId) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        $activity = $repository->findBoosts($this->getPageNb($request), $user);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'html' => $this->renderView('user/_boost_list.html.twig', [
                    'results' => $activity->getCurrentPageResults(),
                    'pagination' => $activity,
                ]),
            ]);
        }

        return $this->render(
            'user/overview.html.twig',
            [
                'user' => $user,
                'results' => $activity->getCurrentPageResults(),
                'pagination' => $activity,
            ],
            $response
        );
    }
}
