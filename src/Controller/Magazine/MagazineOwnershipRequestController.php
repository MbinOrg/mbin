<?php

declare(strict_types=1);

namespace App\Controller\Magazine;

use App\Controller\AbstractController;
use App\Entity\Magazine;
use App\Service\MagazineManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MagazineOwnershipRequestController extends AbstractController
{
    public function __construct(private readonly MagazineManager $manager)
    {
    }

    #[IsGranted('ROLE_USER')]
    #[IsGranted('subscribe', subject: 'magazine')]
    public function toggle(Magazine $magazine, Request $request): Response
    {
        // applying to be owner is only supported for local magazines
        if ($magazine->apId) {
            throw new AccessDeniedException();
        }

        $this->validateCsrf('magazine_ownership_request', $request->getPayload()->get('token'));

        $this->manager->toggleOwnershipRequest($magazine, $this->getUserOrThrow());

        return $this->redirectToRefererOrHome($request);
    }

    #[IsGranted('ROLE_ADMIN')]
    public function accept(Magazine $magazine, Request $request): Response
    {
        $this->validateCsrf('magazine_ownership_request', $request->getPayload()->get('token'));

        $user = $this->getUserOrThrow();
        $this->manager->acceptOwnershipRequest($magazine, $user, $user);

        return $this->redirectToRefererOrHome($request);
    }
}
