<?php

declare(strict_types=1);

namespace App\Controller\Magazine;

use App\Controller\AbstractController;
use App\Form\MagazineType;
use App\Service\IpResolver;
use App\Service\MagazineManager;
use App\Service\SettingsManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MagazineCreateController extends AbstractController
{
    public function __construct(
        private readonly MagazineManager $manager,
        private readonly IpResolver $ipResolver,
        private readonly SettingsManager $settingsManager,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function __invoke(Request $request): Response
    {
        $user = $this->getUserOrThrow();

        if (true === $this->settingsManager->get('MBIN_RESTRICT_MAGAZINE_CREATION') && !$user->isAdmin() && !$user->isModerator()) {
            throw new AccessDeniedException();
        }

        $form = $this->createForm(MagazineType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dto = $form->getData();
            $dto->ip = $this->ipResolver->resolve();
            $magazine = $this->manager->create($dto, $this->getUserOrThrow());

            $this->addFlash('success', 'flash_magazine_new_success');

            return $this->redirectToMagazine($magazine);
        }

        return $this->render(
            'magazine/create.html.twig',
            [
                'user' => $user,
                'form' => $form->createView(),
            ],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }
}
