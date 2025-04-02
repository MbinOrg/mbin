<?php

declare(strict_types=1);

namespace App\Controller\Post;

use App\Controller\AbstractController;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Form\PostType;
use App\PageView\PostCommentPageView;
use App\Repository\PostCommentRepository;
use App\Service\PostManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PostEditController extends AbstractController
{
    public function __construct(
        private readonly PostManager $manager,
        private readonly Security $security,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    #[IsGranted('edit', subject: 'post')]
    public function __invoke(
        #[MapEntity(mapping: ['magazine_name' => 'name'])]
        Magazine $magazine,
        #[MapEntity(id: 'post_id')]
        Post $post,
        Request $request,
        PostCommentRepository $repository,
    ): Response {
        $dto = $this->manager->createDto($post);

        $form = $this->createForm(PostType::class, $dto);
        try {
            // Could thrown an error on event handlers (eg. onPostSubmit if a user upload an incorrect image)
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                if (!$this->isGranted('create_content', $magazine)) {
                    throw new AccessDeniedHttpException();
                }

                $post = $this->manager->edit($post, $dto, $this->getUserOrThrow());

                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse(
                        [
                            'id' => $post->getId(),
                            'html' => $this->renderView(
                                'components/_ajax.html.twig',
                                [
                                    'component' => 'post',
                                    'attributes' => [
                                        'post' => $post,
                                        'showMagazineName' => false,
                                    ],
                                ]
                            ),
                        ]
                    );
                }

                $this->addFlash('success', 'flash_post_edit_success');

                return $this->redirectToPost($post);
            }
        } catch (\Exception $e) {
            // Show an error to the user
            $this->addFlash('error', 'flash_post_edit_error');
        }

        $criteria = new PostCommentPageView($this->getPageNb($request), $this->security);
        $criteria->post = $post;

        if ($request->isXmlHttpRequest()) {
            return $this->getJsonFormResponse(
                $form,
                'post/_form_post.html.twig',
                ['post' => $post, 'edit' => true]
            );
        }

        return $this->render(
            'post/edit.html.twig',
            [
                'magazine' => $magazine,
                'post' => $post,
                'comments' => $repository->findByCriteria($criteria),
                'form' => $form->createView(),
                'criteria' => $criteria,
            ],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }
}
