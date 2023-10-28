<?php

declare(strict_types=1);

namespace App\Controller\Magazine\Panel;

use App\Controller\AbstractController;
use App\DTO\MagazineThemeDto;
use App\Entity\Magazine;
use App\Form\MagazineThemeType;
use App\Service\MagazineManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MagazineThemeController extends AbstractController
{
    public function __construct(private readonly MagazineManager $manager)
    {
    }

    #[IsGranted('ROLE_USER')]
    #[IsGranted('edit', subject: 'magazine')]
    public function __invoke(Magazine $magazine, Request $request): Response
    {
        $dto = new MagazineThemeDto($magazine);

        $form = $this->createForm(MagazineThemeType::class, $dto);

        try {
            // Could thrown an error on event handlers (eg. onPostSubmit if a user upload an incorrect image)
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $magazine = $this->manager->changeTheme($dto);

                $this->addFlash('success', 'magazine_theme_changed_success');
                $this->redirectToRefererOrHome($request);
            }
        } catch (\Exception $e) {
            // Show an error to the user
            $this->addFlash('error', 'magazine_theme_changed_error');
        }

        return $this->render(
            'magazine/panel/theme.html.twig',
            [
                'magazine' => $magazine,
                'form' => $form->createView(),
            ]
        );
    }
}
