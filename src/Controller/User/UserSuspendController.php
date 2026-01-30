<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Service\UserManager;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserSuspendController extends AbstractController
{
    public function __construct(
        private readonly UserManager $userManager,
    ) {
    }

    #[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_MODERATOR")'))]
    public function suspend(User $user, Request $request): Response
    {
        $this->validateCsrf('user_suspend', $request->getPayload()->get('token'));

        $this->userManager->suspend($user);

        $this->addFlash('success', 'account_suspended');

        return $this->redirectToRefererOrHome($request);
    }

    #[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_MODERATOR")'))]
    public function unsuspend(User $user, Request $request): Response
    {
        $this->validateCsrf('user_suspend', $request->getPayload()->get('token'));

        $this->userManager->unsuspend($user);

        $this->addFlash('success', 'account_unsuspended');

        return $this->redirectToRefererOrHome($request);
    }
}
