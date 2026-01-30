<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Service\UserManager;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserBanController extends AbstractController
{
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_MODERATOR")'))]
    public function ban(User $user, UserManager $manager, Request $request): Response
    {
        $this->validateCsrf('user_ban', $request->getPayload()->get('token'));

        $manager->ban($user, $this->getUserOrThrow(), reason: null);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(
                [
                    'isBanned' => true,
                ]
            );
        }

        $this->addFlash('success', 'account_banned');

        return $this->redirectToRefererOrHome($request);
    }

    #[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_MODERATOR")'))]
    public function unban(User $user, UserManager $manager, Request $request): Response
    {
        $this->validateCsrf('user_ban', $request->getPayload()->get('token'));

        $manager->unban($user, $this->getUserOrThrow(), reason: null);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(
                [
                    'isBanned' => false,
                ]
            );
        }

        $this->addFlash('success', 'account_unbanned');

        return $this->redirectToRefererOrHome($request);
    }
}
