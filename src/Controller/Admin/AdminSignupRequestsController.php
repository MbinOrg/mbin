<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use App\Service\UserManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminSignupRequestsController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly UserManager $userManager,
        private readonly NotificationRepository $notificationRepository,
    ) {
    }

    #[IsGranted('ROLE_ADMIN')]
    public function requests(#[MapQueryParameter] ?int $page = 1, #[MapQueryParameter] ?string $username = null): Response
    {
        if (null === $username) {
            $requests = $this->repository->findAllSignupRequestsPaginated($page);
        } else {
            $requests = [];
            if ($signupRequest = $this->repository->findSignupRequest($username)) {
                $requests[] = $signupRequest;
                $user = $this->repository->findOneBy(['username' => $username]);
            }
            // Always mark the notifications as read, even if the user does not have any signup requests anymore
            $this->notificationRepository->markUserSignupNotificationsAsRead($this->getUserOrThrow(), $user);
        }

        return $this->render('admin/signup_requests.html.twig', [
            'requests' => $requests,
            'page' => $page,
            'username' => $username,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    public function approve(#[MapQueryParameter] int $page, #[MapEntity(id: 'id')] User $user): Response
    {
        $this->userManager->approveUserApplication($user);

        return $this->redirectToRoute('admin_signup_requests', ['page' => $page]);
    }

    #[IsGranted('ROLE_ADMIN')]
    public function reject(#[MapQueryParameter] int $page, #[MapEntity(id: 'id')] User $user): Response
    {
        $this->userManager->rejectUserApplication($user);

        return $this->redirectToRoute('admin_signup_requests', ['page' => $page]);
    }
}
