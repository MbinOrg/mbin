<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\ReportDto;
use App\Entity\Contracts\ReportInterface;
use App\Form\ReportType;
use App\Service\ReportManager;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReportController extends AbstractController
{
    public function __construct(
        private readonly ReportManager $manager,
        private readonly TranslatorInterface $translator
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function __invoke(ReportInterface $subject, Request $request): Response
    {
        $dto = (new ReportDto())->create($subject);

        $form = $this->getForm($dto, $subject);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->handleValidSuccessRequest($dto, $request);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->getJsonFormResponse($form, 'report/_form_report.html.twig');
        }

        return $this->render(
            'report/create.html.twig',
            [
                'form' => $form->createView(),
                'magazine' => $subject->magazine,
                'subject' => $subject,
            ]
        );
    }

    private function getForm(ReportDto $dto, ReportInterface $subject): FormInterface
    {
        return $this->createForm(
            ReportType::class,
            $dto,
            [
                'action' => $this->generateUrl($dto->getRouteName(), ['id' => $subject->getId()]),
            ]
        );
    }

    private function handleValidSuccessRequest(ReportDto $dto, Request $request): Response
    {
        $this->manager->report($dto, $this->getUserOrThrow());

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(
                [
                    'success' => true,
                    'html' => "<div class=\"alert alert__info\">{$this->translator->trans('subject_reported')}</div>",
                ]
            );
        }

        // @todo flash message
        return $this->redirectToRefererOrHome($request);
    }
}
