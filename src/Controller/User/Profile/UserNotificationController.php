<?php

declare(strict_types=1);

namespace App\Controller\User\Profile;

use App\Controller\AbstractController;
use App\Repository\NotificationRepository;
use App\Repository\SiteRepository;
use App\Service\NotificationManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserNotificationController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    public function notifications(NotificationRepository $repository, Request $request, SiteRepository $siteRepository): Response
    {
        return $this->render(
            'notifications/front.html.twig',
            [
                'applicationServerKey' => $siteRepository->findAll()[0]->pushPublicKey,
                'notifications' => $repository->findByUser($this->getUserOrThrow(), $this->getPageNb($request)),
            ]
        );
    }

    #[IsGranted('ROLE_USER')]
    public function read(NotificationManager $manager, Request $request): Response
    {
        $manager->markAllAsRead($this->getUserOrThrow());

        return $this->redirectToRefererOrHome($request);
    }

    #[IsGranted('ROLE_USER')]
    public function clear(NotificationManager $manager, Request $request): Response
    {
        $manager->clear($this->getUserOrThrow());

        return $this->redirectToRefererOrHome($request);
    }
}
