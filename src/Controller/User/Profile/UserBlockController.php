<?php

declare(strict_types=1);

namespace App\Controller\User\Profile;

use App\Controller\AbstractController;
use App\Repository\DomainRepository;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserBlockController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    public function magazines(MagazineRepository $repository, Request $request): Response
    {
        $user = $this->getUserOrThrow();

        return $this->render(
            'user/settings/block_magazines.html.twig',
            [
                'user' => $user,
                'magazines' => $repository->findBlockedMagazines($this->getPageNb($request), $user),
            ]
        );
    }

    #[IsGranted('ROLE_USER')]
    public function users(UserRepository $repository, Request $request): Response
    {
        $user = $this->getUserOrThrow();

        return $this->render(
            'user/settings/block_users.html.twig',
            [
                'user' => $user,
                'users' => $repository->findBlockedUsers($this->getPageNb($request), $user),
            ]
        );
    }

    #[IsGranted('ROLE_USER')]
    public function domains(DomainRepository $repository, Request $request): Response
    {
        $user = $this->getUserOrThrow();

        return $this->render(
            'user/settings/block_domains.html.twig',
            [
                'user' => $user,
                'domains' => $repository->findBlockedDomains($this->getPageNb($request), $ $user),
            ]
        );
    }
}
