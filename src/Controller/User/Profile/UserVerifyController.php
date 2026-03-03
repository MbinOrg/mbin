<?php

declare(strict_types=1);

namespace App\Controller\User\Profile;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Service\UserManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserVerifyController extends AbstractController
{
    public function __construct(
        private readonly UserManager $manager,
    ) {
    }

    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(#[MapEntity(mapping: ['username' => 'username'])] User $user, Request $request): Response
    {
        $this->validateCsrf('user_verify', $request->getPayload()->get('token'));

        $this->manager->adminUserVerify($user);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(
                [
                    'isVerified' => true,
                ]
            );
        }

        return $this->redirectToRefererOrHome($request);
    }
}
