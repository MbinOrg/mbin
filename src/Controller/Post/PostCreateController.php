<?php

declare(strict_types=1);

namespace App\Controller\Post;

use App\Controller\AbstractController;
use App\Form\PostType;
use App\Repository\Criteria;
use App\Service\IpResolver;
use App\Service\PostManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PostCreateController extends AbstractController
{
    public function __construct(
        private readonly PostManager $manager,
        private readonly IpResolver $ipResolver
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function __invoke(Request $request): Response
    {
        $form = $this->createForm(PostType::class);

        try {
            // Could thrown an error on event handlers (eg. onPostSubmit if a user upload an incorrect image)
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $dto = $form->getData();
                $dto->ip = $this->ipResolver->resolve();

                if (!$this->isGranted('create_content', $dto->magazine)) {
                    throw new AccessDeniedHttpException();
                }

                $this->manager->create($dto, $this->getUserOrThrow());

                $this->addFlash('success', 'flash_post_new_success');

                return $this->redirectToRoute(
                    'magazine_posts',
                    [
                        'name' => $dto->magazine->name,
                        'sortBy' => Criteria::SORT_NEW,
                    ]
                );
            }
        } catch (\Exception $e) {
            // Show an error to the user
            $this->addFlash('error', 'flash_post_new_error');
        }

        return $this->render('post/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
