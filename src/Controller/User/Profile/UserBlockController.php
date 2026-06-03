<?php

declare(strict_types=1);

namespace App\Controller\User\Profile;

use App\Controller\AbstractController;
use App\Entity\InstanceBlock;
use App\Repository\DomainRepository;
use App\Repository\InstanceBlockRepository;
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
                'domains' => $repository->findBlockedDomains($this->getPageNb($request), $user),
            ]
        );
    }

    #[IsGranted('ROLE_USER')]
    public function instances(InstanceBlockRepository $repository, Request $request): Response
    {
        $user = $this->getUserOrThrow();

        $blocks = $repository->findBlocksForUser($user);
        $instances = \array_map(function (InstanceBlock $block) {
            return $block->instance;
        }, $blocks);

        return $this->render(
            'user/settings/block_instances.html.twig',
            [
                'user' => $user,
                'blocks' => $blocks,
                'instances' => $instances,
            ]
        );
    }
}
