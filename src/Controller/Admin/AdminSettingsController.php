<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Form\SettingsType;
use App\Service\SettingsManager;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminSettingsController extends AbstractController
{
    public function __construct(
        private readonly SettingsManager $settings,
        private readonly TranslatorInterface $translator)
    {
    }

    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(Request $request): Response
    {
        $dto = $this->settings->getDto();

        $form = $this->createForm(SettingsType::class, $dto);

        if ($dto->MBIN_SIDEBAR_SECTIONS_LOCAL_ONLY) {
            $form->get('MBIN_SIDEBAR_SECTIONS_LOCAL_ONLY')->addError(new FormError($this->translator->trans('local_only_performance_warning')));
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->settings->save($dto);

            return $this->redirectToRefererOrHome($request);
        }

        return $this->render('admin/settings.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
