<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Controller\AbstractController;
use App\Service\UserManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserCoverDeleteController extends AbstractController
{
    public function __construct(private readonly UserManager $userManager)
    {
    }

    #[IsGranted('ROLE_USER')]
    public function __invoke(Request $request): Response
    {
        $this->denyAccessUnlessGranted('edit_profile', $this->getUserOrThrow());

        $user = $this->getUserOrThrow();
        $this->userManager->detachCover($user);
        /*
         * Call edit so the @see UserEditedEvent is triggered and the changes are federated
         */
        $this->userManager->edit($user, $this->userManager->createDto($user));

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(
                [
                    'success' => true,
                ]
            );
        }

        return $this->redirectToRefererOrHome($request);
    }
}
