<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Service\UserManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserDeleteController extends AbstractController
{
    #[IsGranted('ROLE_ADMIN')]
    public function deleteAccount(User $user, UserManager $manager, Request $request): Response
    {
        $this->validateCsrf('user_delete_account', $request->request->get('token'));

        $manager->delete($user);

        return $this->redirectToRoute('front');
    }

    #[IsGranted('ROLE_ADMIN')]
    public function scheduleDeleteAccount(User $user, UserManager $manager, Request $request): Response
    {
        $this->validateCsrf('schedule_user_delete_account', $request->request->get('token'));

        $manager->deleteRequest($user, false);

        return $this->redirectToRoute('user_overview', ['username' => $user->username]);
    }

    #[IsGranted('ROLE_ADMIN')]
    public function removeScheduleDeleteAccount(User $user, UserManager $manager, Request $request): Response
    {
        $this->validateCsrf('remove_schedule_user_delete_account', $request->request->get('token'));

        $manager->removeDeleteRequest($user);

        return $this->redirectToRoute('user_overview', ['username' => $user->username]);
    }
}
