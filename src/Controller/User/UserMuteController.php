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

class UserMuteController extends AbstractController
{
    public function __construct(
        private readonly UserManager $userManager
    ) {
    }

    #[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_MODERATOR")'))]
    public function mute(User $user, Request $request): Response
    {
        $this->validateCsrf('user_mute', $request->request->get('token'));

        $this->userManager->mute($user);

        $this->addFlash('success', 'account_muted');

        return $this->redirectToRefererOrHome($request);
    }

    #[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_MODERATOR")'))]
    public function unmute(User $user, Request $request): Response
    {
        $this->validateCsrf('user_mute', $request->request->get('token'));

        $this->userManager->unmute($user);

        $this->addFlash('success', 'account_unmuted');

        return $this->redirectToRefererOrHome($request);
    }
}
