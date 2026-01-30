<?php

declare(strict_types=1);

namespace App\Controller\Post;

use App\Controller\AbstractController;
use App\DTO\PostDto;
use App\Exception\InstanceBannedException;
use App\Form\PostType;
use App\Repository\Criteria;
use App\Repository\MagazineRepository;
use App\Service\IpResolver;
use App\Service\PostManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PostCreateController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PostManager $manager,
        private readonly MagazineRepository $magazineRepository,
        private readonly IpResolver $ipResolver,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function __invoke(Request $request): Response
    {
        $dto = new PostDto();
        // check if teh "random" magazine exists and if so, use it
        $randomMagazine = $this->magazineRepository->findOneByName('random');
        if(null !== $randomMagazine) {
            $dto->magazine = $randomMagazine;
        }

        $form = $this->createForm(PostType::class)->setData($dto);
        $user = $this->getUserOrThrow();
        try {
            // Could thrown an error on event handlers (eg. onPostSubmit if a user upload an incorrect image)
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $dto = $form->getData();
                $dto->ip = $this->ipResolver->resolve();

                if (!$this->isGranted('create_content', $dto->magazine)) {
                    throw new AccessDeniedHttpException();
                }

                $this->manager->create($dto, $user);

                $this->addFlash('success', 'flash_post_new_success');

                return $this->redirectToRoute(
                    'magazine_posts',
                    [
                        'name' => $dto->magazine->name,
                        'sortBy' => Criteria::SORT_NEW,
                    ]
                );
            }
        } catch (InstanceBannedException) {
            $this->addFlash('error', 'flash_instance_banned_error');
        } catch (\Exception $e) {
            $this->logger->error('{user} tried to create a post, but an exception occurred: {ex} - {message}', ['user' => $user->username, 'ex' => \get_class($e), 'message' => $e->getMessage(), 'stacktrace' => $e->getTrace()]);
            // Show an error to the user
            $this->addFlash('error', 'flash_post_new_error');
        }

        return $this->render('post/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
