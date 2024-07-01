<?php

declare(strict_types=1);

namespace App\Controller\User\Profile;

use App\Controller\AbstractController;
use App\Repository\MagazineRepository;
use App\Repository\NotificationRepository;
use App\Repository\ReportRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserReportsController extends AbstractController
{
    public const MODERATED = 'moderated';

    public function __construct(
        private readonly ReportRepository $repository,
        private readonly NotificationRepository $notificationRepository,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function __invoke(MagazineRepository $repository, Request $request, string $status): Response
    {
        $user = $this->getUserOrThrow();
        $reports = $this->repository->findByUserPaginated($user, $this->getPageNb($request), status: $status);
        $this->notificationRepository->markOwnReportNotificationsAsRead($this->getUserOrThrow());

        return $this->render(
            'user/settings/reports.html.twig',
            [
                'user' => $user,
                'reports' => $reports,
            ]
        );
    }
}
