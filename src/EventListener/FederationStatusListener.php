<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\SettingsManager;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FederationStatusListener
{
    public function __construct(private readonly SettingsManager $settingsManager)
    {
    }

    public function onKernelController(ControllerEvent $event)
    {
        if (!$event->isMainRequest() || $this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');

        if (str_starts_with($route, 'ap_') && 'ap_node_info' !== $route && 'ap_node_info_v2' !== $route) {
            throw new NotFoundHttpException();
        }
    }
}
