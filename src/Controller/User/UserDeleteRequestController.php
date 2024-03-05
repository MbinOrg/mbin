<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Controller\AbstractController;
use App\Service\IpResolver;
use App\Service\UserManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserDeleteRequestController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly UserManager $userManager,
        private readonly RateLimiterFactory $userDeleteLimiter,
        private readonly IpResolver $ipResolver
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function request(Request $request): Response
    {
        $this->denyAccessUnlessGranted('edit_profile', $this->getUserOrThrow());

        $this->validateCsrf('user_delete', $request->request->get('token'));

        $limiter = $this->userDeleteLimiter->create($this->ipResolver->resolve());
        if (false === $limiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        $this->userManager->deleteRequest($this->getUserOrThrow());
        $this->security->logout();

        $this->addFlash('success', 'delete_account_request_send');

        return $this->redirectToRefererOrHome($request);
    }

    #[IsGranted('ROLE_USER')]
    public function revoke(Request $request): Response
    {
        $this->denyAccessUnlessGranted('edit_profile', $this->getUserOrThrow());

        $this->validateCsrf('user_delete', $request->request->get('token'));

        $limiter = $this->userDeleteLimiter->create($this->ipResolver->resolve());
        if (false === $limiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        $this->userManager->revokeDeleteRequest($this->getUserOrThrow());

        $this->addFlash('success', 'delete_account_request_revoke');

        return $this->redirectToRefererOrHome($request);
    }
}
