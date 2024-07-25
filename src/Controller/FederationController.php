<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\InstanceRepository;
use App\Service\SettingsManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FederationController extends AbstractController
{
    public function __invoke(InstanceRepository $instanceRepository, SettingsManager $settings, Request $request): Response
    {
        if (!$settings->get('KBIN_FEDERATION_PAGE_ENABLED')) {
            return $this->redirectToRoute('front');
        }

        $allowedInstances = $instanceRepository->getAllowedInstances();
        $defederatedInstances = $instanceRepository->getBannedInstances();
        $deadInstances = $instanceRepository->getDeadInstances();

        return $this->render(
            'page/federation.html.twig',
            [
                'allowedInstances' => $allowedInstances,
                'defederatedInstances' => $defederatedInstances,
                'deadInstances' => $deadInstances,
            ]
        );
    }
}
