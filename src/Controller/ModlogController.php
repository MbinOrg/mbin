<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\ModlogFilterDto;
use App\Entity\Magazine;
use App\Form\ModlogFilterType;
use App\Repository\MagazineLogRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ModlogController extends AbstractController
{
    public function __construct(
        private readonly MagazineLogRepository $magazineLogRepository,
    ) {
    }

    public function instance(Request $request): Response
    {
        $dto = new ModlogFilterDto();
        $dto->magazine = null;
        $form = $this->createForm(ModlogFilterType::class, $dto, ['method' => 'GET']);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var ModlogFilterDto $dto */
            $dto = $form->getData();

            if (null !== $dto->magazine) {
                return $this->redirectToRoute('magazine_modlog', ['name' => $dto->magazine->name]);
            }
            $logs = $this->magazineLogRepository->findByCustom($this->getPageNb($request), types: $dto->types);
        } else {
            $logs = $this->magazineLogRepository->findByCustom($this->getPageNb($request));
        }

        return $this->render(
            'modlog/front.html.twig',
            [
                'logs' => $logs,
                'form' => $form,
            ]
        );
    }

    public function magazine(#[MapEntity] ?Magazine $magazine, Request $request): Response
    {
        $dto = new ModlogFilterDto();
        $dto->magazine = $magazine;
        $form = $this->createForm(ModlogFilterType::class, $dto, ['method' => 'GET']);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var ModlogFilterDto $dto */
            $dto = $form->getData();
            if (null === $dto->magazine) {
                return $this->redirectToRoute('modlog');
            } elseif ($dto->magazine?->name !== $magazine->name) {
                return $this->redirectToRoute('magazine_modlog', ['name' => $dto->magazine->name]);
            }
            $logs = $this->magazineLogRepository->findByCustom($this->getPageNb($request), types: $dto->types, magazine: $magazine);
        } else {
            $logs = $this->magazineLogRepository->findByCustom($this->getPageNb($request), magazine: $magazine);
        }

        return $this->render(
            'modlog/front.html.twig',
            [
                'magazine' => $magazine,
                'logs' => $logs,
                'form' => $form,
            ]
        );
    }
}
