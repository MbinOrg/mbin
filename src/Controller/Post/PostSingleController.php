<?php

declare(strict_types=1);

namespace App\Controller\Post;

use App\Controller\AbstractController;
use App\Controller\Traits\PrivateContentTrait;
use App\Controller\User\ThemeSettingsController;
use App\DTO\PostCommentDto;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Event\Post\PostHasBeenSeenEvent;
use App\Form\PostCommentType;
use App\PageView\PostCommentPageView;
use App\Repository\Criteria;
use App\Repository\ImageRepository;
use App\Repository\PostCommentRepository;
use App\Service\MentionManager;
use Pagerfanta\PagerfantaInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PostSingleController extends AbstractController
{
    use PrivateContentTrait;

    public function __construct(
        private readonly PostCommentRepository $commentRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly MentionManager $mentionManager,
        private readonly Security $security,
        private readonly ImageRepository $imageRepository,
    ) {
    }

    public function __invoke(
        #[MapEntity(mapping: ['magazine_name' => 'name'])]
        Magazine $magazine,
        #[MapEntity(id: 'post_id')]
        Post $post,
        ?string $sortBy,
        Request $request,
    ): Response {
        if ($post->magazine !== $magazine) {
            return $this->redirectToRoute(
                'post_single',
                ['magazine_name' => $post->magazine->name, 'post_id' => $post->getId(), 'slug' => $post->slug],
                301
            );
        }

        $response = new Response();
        if ($post->apId && $post->user->apId) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        $this->handlePrivateContent($post);

        $images = [];
        if ($post->image) {
            $images[] = $post->image;
        }
        $images = array_merge($images, $this->commentRepository->findImagesByPost($post));
        $this->imageRepository->redownloadImagesIfNecessary($images);

        $criteria = new PostCommentPageView($this->getPageNb($request), $this->security);
        $criteria->showSortOption($criteria->resolveSort($sortBy));
        $criteria->content = Criteria::CONTENT_MICROBLOG;
        $criteria->post = $post;
        $criteria->onlyParents = true;
        $criteria->perPage = 25;

        if (ThemeSettingsController::CHAT === $request->cookies->get(
            ThemeSettingsController::POST_COMMENTS_VIEW
        )) {
            $criteria->showSortOption(Criteria::SORT_OLD);
            $criteria->perPage = 100;
            $criteria->onlyParents = false;
        }

        $comments = $this->commentRepository->findByCriteria($criteria);

        $commentObjects = [...$comments->getCurrentPageResults()];
        $this->commentRepository->hydrate(...$commentObjects);
        $this->commentRepository->hydrateChildren(...$commentObjects);

        $this->dispatcher->dispatch(new PostHasBeenSeenEvent($post));

        if ($request->isXmlHttpRequest()) {
            return $this->getJsonResponse($magazine, $post, $comments);
        }

        $dto = new PostCommentDto();
        if ($this->getUser() && $this->getUser()->addMentionsPosts && $post->user !== $this->getUser()) {
            $dto->body = $this->mentionManager->addHandle([$post->user->username])[0];
        }

        return $this->render(
            'post/single.html.twig',
            [
                'magazine' => $magazine,
                'post' => $post,
                'comments' => $comments,
                'criteria' => $criteria,
                'form' => $this->createForm(
                    PostCommentType::class,
                    $dto,
                    [
                        'parentLanguage' => $post->lang,
                    ]
                )->createView(),
            ],
            $response
        );
    }

    private function getJsonResponse(Magazine $magazine, Post $post, PagerfantaInterface $comments): JsonResponse
    {
        return new JsonResponse(
            [
                'html' => $this->renderView(
                    'post/_single_popup.html.twig',
                    [
                        'magazine' => $magazine,
                        'post' => $post,
                        'comments' => $comments,
                    ]
                ),
            ]
        );
    }
}
