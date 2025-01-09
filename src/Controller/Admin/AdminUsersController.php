<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminUsersController extends AbstractController
{
    public function __construct(private readonly UserRepository $repository, private readonly RequestStack $request)
    {
    }

    #[IsGranted('ROLE_ADMIN')]
    public function active(?bool $withFederated = null): Response
    {
        return $this->render(
            'admin/users.html.twig',
            [
                'users' => $this->repository->findAllActivePaginated(
                    (int) $this->request->getCurrentRequest()->get('p', 1),
                    !($withFederated ?? false)
                ),
                'withFederated' => $withFederated,
            ]
        );
    }

    #[IsGranted('ROLE_ADMIN')]
    public function inactive(): Response
    {
        return $this->render(
            'admin/users.html.twig',
            [
                'users' => $this->repository->findAllInactivePaginated(
                    (int) $this->request->getCurrentRequest()->get('p', 1)
                ),
            ]
        );
    }

    #[IsGranted('ROLE_ADMIN')]
    public function suspended(?bool $withFederated = null): Response
    {
        return $this->render(
            'admin/users.html.twig',
            [
                'users' => $this->repository->findAllSuspendedPaginated(
                    (int) $this->request->getCurrentRequest()->get('p', 1),
                    !($withFederated ?? false)
                ),
                'withFederated' => $withFederated,
            ]
        );
    }

    #[IsGranted('ROLE_ADMIN')]
    public function banned(?bool $withFederated = null): Response
    {
        return $this->render(
            'admin/users.html.twig',
            [
                'users' => $this->repository->findAllBannedPaginated(
                    (int) $this->request->getCurrentRequest()->get('p', 1),
                    !($withFederated ?? false)
                ),
                'withFederated' => $withFederated,
            ]
        );
    }
}
