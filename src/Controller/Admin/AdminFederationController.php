<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\DTO\ConfirmDefederationDto;
use App\DTO\FederationSettingsDto;
use App\Form\ConfirmDefederationType;
use App\Form\FederationSettingsType;
use App\Repository\InstanceRepository;
use App\Service\InstanceManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminFederationController extends AbstractController
{
    public function __construct(
        private readonly SettingsManager $settingsManager,
        private readonly InstanceRepository $instanceRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly InstanceManager $instanceManager,
    ) {
    }

    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(Request $request): Response
    {
        $settings = $this->settingsManager->getDto();
        $dto = new FederationSettingsDto(
            $settings->KBIN_FEDERATION_ENABLED,
            $settings->MBIN_USE_FEDERATION_ALLOW_LIST,
            $settings->KBIN_FEDERATION_PAGE_ENABLED,
        );

        $form = $this->createForm(FederationSettingsType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var FederationSettingsDto $dto */
            $dto = $form->getData();
            $settings->KBIN_FEDERATION_ENABLED = $dto->federationEnabled;
            $settings->MBIN_USE_FEDERATION_ALLOW_LIST = $dto->federationUsesAllowList;
            $settings->KBIN_FEDERATION_PAGE_ENABLED = $dto->federationPageEnabled;
            $this->settingsManager->save($settings);

            return $this->redirectToRoute('admin_federation');
        }

        $useAllowList = $this->settingsManager->getUseAllowList();

        return $this->render(
            'admin/federation.html.twig',
            [
                'form' => $form->createView(),
                'useAllowList' => $useAllowList,
                'instances' => $useAllowList ? $this->settingsManager->getAllowedInstances() : $this->settingsManager->getBannedInstances(),
                'allInstances' => $this->instanceRepository->findAllOrdered(),
            ]
        );
    }

    #[IsGranted('ROLE_ADMIN')]
    public function banInstance(#[MapQueryParameter] string $instanceDomain, Request $request): Response
    {
        $instance = $this->instanceRepository->getOrCreateInstance($instanceDomain);

        $form = $this->createForm(ConfirmDefederationType::class, new ConfirmDefederationDto());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var ConfirmDefederationDto $dto */
            $dto = $form->getData();
            if ($dto->confirm) {
                $this->instanceManager->banInstance($instance);

                return $this->redirectToRoute('admin_federation');
            } else {
                $this->addFlash('error', 'flash_error_defederation_must_confirm');
            }
        }

        return $this->render('admin/federation_defederate_instance.html.twig', [
            'form' => $form->createView(),
            'instance' => $instance,
            'counts' => $this->instanceRepository->getInstanceCounts($instance),
            'useAllowList' => $this->settingsManager->getUseAllowList(),
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }

    #[IsGranted('ROLE_ADMIN')]
    public function unbanInstance(#[MapQueryParameter] string $instanceDomain): Response
    {
        $instance = $this->instanceRepository->getOrCreateInstance($instanceDomain);
        $this->instanceManager->unbanInstance($instance);

        return $this->redirectToRoute('admin_federation');
    }

    #[IsGranted('ROLE_ADMIN')]
    public function allowInstance(#[MapQueryParameter] string $instanceDomain): Response
    {
        $instance = $this->instanceRepository->getOrCreateInstance($instanceDomain);
        $this->instanceManager->allowInstanceFederation($instance);

        return $this->redirectToRoute('admin_federation');
    }

    #[IsGranted('ROLE_ADMIN')]
    public function denyInstance(#[MapQueryParameter] string $instanceDomain, Request $request): Response
    {
        $instance = $this->instanceRepository->getOrCreateInstance($instanceDomain);

        $form = $this->createForm(ConfirmDefederationType::class, new ConfirmDefederationDto());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var ConfirmDefederationDto $dto */
            $dto = $form->getData();
            if ($dto->confirm) {
                $this->instanceManager->denyInstanceFederation($instance);

                return $this->redirectToRoute('admin_federation');
            } else {
                $this->addFlash('error', 'flash_error_defederation_must_confirm');
            }
        }

        return $this->render('admin/federation_defederate_instance.html.twig', [
            'form' => $form->createView(),
            'instance' => $instance,
            'counts' => $this->instanceRepository->getInstanceCounts($instance),
            'useAllowList' => $this->settingsManager->getUseAllowList(),
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }
}
