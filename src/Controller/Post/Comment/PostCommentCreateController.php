<?php

declare(strict_types=1);

namespace App\Controller\Post\Comment;

use App\Controller\AbstractController;
use App\DTO\PostCommentDto;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Exception\InstanceBannedException;
use App\Exception\TagBannedException;
use App\Exception\UserBannedException;
use App\Form\PostCommentType;
use App\PageView\PostCommentPageView;
use App\Repository\PostCommentRepository;
use App\Service\IpResolver;
use App\Service\MentionManager;
use App\Service\PostCommentManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PostCommentCreateController extends AbstractController
{
    use PostCommentResponseTrait;

    public function __construct(
        private readonly PostCommentManager $manager,
        private readonly PostCommentRepository $repository,
        private readonly IpResolver $ipResolver,
        private readonly MentionManager $mentionManager,
        private readonly Security $security,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    #[IsGranted('comment', subject: 'post')]
    public function __invoke(
        #[MapEntity(mapping: ['magazine_name' => 'name'])]
        Magazine $magazine,
        #[MapEntity(id: 'post_id')]
        Post $post,
        #[MapEntity(id: 'parent_comment_id')]
        ?PostComment $parent,
        Request $request,
    ): Response {
        $form = $this->getForm($post, $parent);
        try {
            // Could thrown an error on event handlers (eg. onPostSubmit if a user upload an incorrect image)
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $dto = $form->getData();
                $dto->post = $post;
                $dto->magazine = $magazine;
                $dto->parent = $parent;
                $dto->ip = $this->ipResolver->resolve();

                if (!$this->isGranted('create_content', $dto->magazine)) {
                    throw new AccessDeniedHttpException();
                }

                return $this->handleValidRequest($dto, $request);
            }
        } catch (InstanceBannedException) {
            $this->addFlash('error', 'flash_instance_banned_error');
        } catch (\Exception $e) {
            // Show an error to the user
            $this->addFlash('error', 'flash_comment_new_error');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->getJsonFormResponse(
                $form,
                'post/comment/_form_comment.html.twig',
                ['post' => $post, 'parent' => $parent]
            );
        }

        $user = $this->getUserOrThrow();
        $criteria = new PostCommentPageView($this->getPageNb($request), $this->security);
        $criteria->post = $post;

        $comments = $this->repository->findByCriteria($criteria);

        return $this->render(
            'post/comment/create.html.twig',
            [
                'user' => $user,
                'magazine' => $magazine,
                'post' => $post,
                'comments' => $comments,
                'parent' => $parent,
                'form' => $form->createView(),
            ],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }

    private function getForm(Post $post, ?PostComment $parent): FormInterface
    {
        $dto = new PostCommentDto();

        if ($parent && $this->getUser()->addMentionsPosts) {
            $handle = $this->mentionManager->addHandle([$parent->user->username])[0];

            if ($parent->user !== $this->getUser()) {
                $dto->body = $handle;
            } else {
                $dto->body .= PHP_EOL;
            }

            if ($parent->mentions) {
                $mentions = $this->mentionManager->addHandle($parent->mentions);
                $mentions = array_filter(
                    $mentions,
                    fn (string $mention) => $mention !== $handle && $mention !== $this->mentionManager->addHandle([$this->getUser()->username])[0]
                );

                $dto->body .= PHP_EOL.PHP_EOL;
                $dto->body .= implode(' ', array_unique($mentions));
            }
        } elseif ($this->getUser()->addMentionsPosts) {
            if ($post->user !== $this->getUser()) {
                $dto->body = $this->mentionManager->addHandle([$post->user->username])[0];
            }
        }

        return $this->createForm(
            PostCommentType::class,
            $dto,
            [
                'action' => $this->generateUrl(
                    'post_comment_create',
                    [
                        'magazine_name' => $post->magazine->name,
                        'post_id' => $post->getId(),
                        'parent_comment_id' => $parent?->getId(),
                    ]
                ),
                'parentLanguage' => $parent?->lang ?? $post->lang,
            ]
        );
    }

    /**
     * @throws InstanceBannedException
     * @throws TagBannedException
     * @throws UserBannedException
     */
    private function handleValidRequest(PostCommentDto $dto, Request $request): Response
    {
        $comment = $this->manager->create($dto, $this->getUserOrThrow());

        if ($request->isXmlHttpRequest()) {
            return $this->getPostCommentJsonSuccessResponse($comment);
        }

        $this->addFlash('success', 'flash_comment_new_success');

        return $this->redirectToPost($comment->post);
    }
}
