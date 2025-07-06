<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Repository\ReputationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\Query\Expr\OrderBy;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminUsersController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly RequestStack $request,
        private readonly ReputationRepository $reputationRepository,
    ) {
    }

    #[IsGranted('ROLE_ADMIN')]
    public function active(
        ?bool $withFederated = null,
        #[MapQueryParameter] int $p = 1,
        #[MapQueryParameter] string $sort = 'ASC',
        #[MapQueryParameter] string $field = 'createdAt',
        #[MapQueryParameter] ?string $search = null,
    ): Response {
        $users = $this->repository
            ->findAllActivePaginated(
                page: $p,
                onlyLocal: !($withFederated ?? false),
                searchTerm: $search,
                orderBy: new OrderBy("u.$field", $sort),
            );
        $userIds = array_map(fn (User $user) => $user->getId(), [...$users]);
        $attitudes = $this->reputationRepository->getUserAttitudes(...$userIds);

        return $this->render(
            'admin/users.html.twig',
            [
                'users' => $users,
                'withFederated' => $withFederated,
                'sortField' => $field,
                'order' => $sort,
                'searchTerm' => $search,
                'attitudes' => $attitudes,
            ]
        );
    }

    #[IsGranted('ROLE_ADMIN')]
    public function inactive(
        #[MapQueryParameter] int $p = 1,
        #[MapQueryParameter] string $sort = 'ASC',
        #[MapQueryParameter] string $field = 'createdAt',
        #[MapQueryParameter] ?string $search = null,
    ): Response {
        return $this->render(
            'admin/users.html.twig',
            [
                'users' => $this->repository->findAllInactivePaginated(
                    $p,
                    searchTerm: $search,
                    orderBy: new OrderBy("u.$field", $sort)
                ),
                'sortField' => $field,
                'order' => $sort,
                'searchTerm' => $search,
            ]
        );
    }

    #[IsGranted('ROLE_ADMIN')]
    public function suspended(
        ?bool $withFederated = null,
        #[MapQueryParameter] int $p = 1,
        #[MapQueryParameter] string $sort = 'ASC',
        #[MapQueryParameter] string $field = 'createdAt',
        #[MapQueryParameter] ?string $search = null,
    ): Response {
        return $this->render(
            'admin/users.html.twig',
            [
                'users' => $this->repository->findAllSuspendedPaginated(
                    $p,
                    onlyLocal: !($withFederated ?? false),
                    searchTerm: $search,
                    orderBy: new OrderBy("u.$field", $sort)
                ),
                'withFederated' => $withFederated,
                'sortField' => $field,
                'order' => $sort,
                'searchTerm' => $search,
            ]
        );
    }

    #[IsGranted('ROLE_ADMIN')]
    public function banned(
        ?bool $withFederated = null,
        #[MapQueryParameter] int $p = 1,
        #[MapQueryParameter] string $sort = 'ASC',
        #[MapQueryParameter] string $field = 'createdAt',
        #[MapQueryParameter] ?string $search = null,
    ): Response {
        return $this->render(
            'admin/users.html.twig',
            [
                'users' => $this->repository->findAllBannedPaginated(
                    $p,
                    onlyLocal: !($withFederated ?? false),
                    searchTerm: $search,
                    orderBy: new OrderBy("u.$field", $sort),
                ),
                'withFederated' => $withFederated,
                'sortField' => $field,
                'order' => $sort,
                'searchTerm' => $search,
            ]
        );
    }
}
