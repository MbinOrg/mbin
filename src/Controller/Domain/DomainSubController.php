<?php

declare(strict_types=1);

namespace App\Controller\Domain;

use App\Controller\AbstractController;
use App\Entity\Domain;
use App\Service\DomainManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DomainSubController extends AbstractController
{
    public function __construct(
        private readonly DomainManager $manager,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function subscribe(Domain $domain, Request $request): Response
    {
        $this->manager->subscribe($domain, $this->getUserOrThrow());

        if ($request->isXmlHttpRequest()) {
            return $this->getJsonResponse($domain);
        }

        return $this->redirectToRefererOrHome($request);
    }

    #[IsGranted('ROLE_USER')]
    public function unsubscribe(Domain $domain, Request $request): Response
    {
        $this->manager->unsubscribe($domain, $this->getUserOrThrow());

        if ($request->isXmlHttpRequest()) {
            return $this->getJsonResponse($domain);
        }

        return $this->redirectToRefererOrHome($request);
    }

    private function getJsonResponse(Domain $domain): JsonResponse
    {
        return new JsonResponse(
            [
                'html' => $this->renderView(
                    'components/_ajax.html.twig',
                    [
                        'component' => 'domain_sub',
                        'attributes' => [
                            'domain' => $domain,
                        ],
                    ]
                ),
            ]
        );
    }
}
