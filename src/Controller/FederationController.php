<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\InstanceBlockRepository;
use App\Repository\InstanceRepository;
use App\Service\InstanceManager;
use App\Service\SettingsManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FederationController extends AbstractController
{
    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly InstanceRepository $instanceRepository,
        private readonly InstanceBlockRepository $instanceBlockRepository,
        private readonly InstanceManager $instanceManager,
        private readonly SettingsManager $settings,
    ) {
    }

    public function __invoke(
        Request $request,
    ): Response {
        if (!$this->settings->get('KBIN_FEDERATION_PAGE_ENABLED')) {
            return $this->redirectToRoute('front');
        }

        $allowedInstances = $this->instanceRepository->getAllowedInstances($this->settings->getUseAllowList());
        $defederatedInstances = $this->instanceRepository->getBannedInstances();
        $deadInstances = $this->instanceRepository->getDeadInstances();

        $user = $this->getUser();
        if (null !== $user) {
            $userInstanceBlocks = $this->instanceBlockRepository->findBlocksForUser($user);
        } else {
            $userInstanceBlocks = null;
        }

        return $this->render(
            'page/federation.html.twig',
            [
                'allowedInstances' => $allowedInstances,
                'defederatedInstances' => $defederatedInstances,
                'deadInstances' => $deadInstances,
                'userInstanceBlocks' => $userInstanceBlocks,
            ]
        );
    }

    #[IsGranted('ROLE_USER')]
    public function userBlockInstance(#[MapQueryParameter] string $instanceDomain): Response
    {
        $user = $this->getUserOrThrow();
        $instance = $this->instanceRepository->findOneBy(['domain' => $instanceDomain]);

        if (null === $instance) {
            throw new NotFoundHttpException('instance '.$instanceDomain.' not found');
        }

        $this->instanceManager->blockInstance($instance, $user);

        return $this->redirectToRoute('page_federation');
    }

    #[IsGranted('ROLE_USER')]
    public function userUnblockInstance(
        #[MapQueryParameter]
        string $instanceDomain,
        #[MapQueryParameter]
        ?string $redirTarget,
    ): Response {
        $user = $this->getUserOrThrow();
        $instance = $this->instanceRepository->findOneBy(['domain' => $instanceDomain]);

        if (null === $instance) {
            throw new NotFoundHttpException('instance '.$instanceDomain.' not found');
        }

        $this->instanceManager->unblockInstance($instance, $user);

        return match ($redirTarget) {
            'blocks' => $this->redirectToRoute('user_settings_instance_blocks'),
            default => $this->redirectToRoute('page_federation'),
        };
    }
}
