<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Entity\User;
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
    ) {
    }

    #[IsGranted('ROLE_ADMIN')]
    public function requests(#[MapQueryParameter] int $page): Response
    {
        $requests = $this->repository->findAllSignupRequestsPaginated($page);

        return $this->render('admin/signup_requests.html.twig', [
            'requests' => $requests,
            'page' => $page,
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
