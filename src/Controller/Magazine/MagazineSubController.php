<?php

declare(strict_types=1);

namespace App\Controller\Magazine;

use App\Controller\AbstractController;
use App\Entity\Magazine;
use App\Service\MagazineManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MagazineSubController extends AbstractController
{
    public function __construct(private readonly MagazineManager $manager)
    {
    }

    #[IsGranted('ROLE_USER')]
    #[IsGranted('subscribe', subject: 'magazine')]
    public function subscribe(Magazine $magazine, Request $request): Response
    {
        $this->manager->subscribe($magazine, $this->getUserOrThrow());

        if ($request->isXmlHttpRequest()) {
            return $this->getJsonResponse($magazine);
        }

        return $this->redirectToRefererOrHome($request);
    }

    #[IsGranted('ROLE_USER')]
    #[IsGranted('subscribe', subject: 'magazine')]
    public function unsubscribe(Magazine $magazine, Request $request): Response
    {
        $this->manager->unsubscribe($magazine, $this->getUserOrThrow());

        if ($request->isXmlHttpRequest()) {
            return $this->getJsonResponse($magazine);
        }

        return $this->redirectToRefererOrHome($request);
    }

    private function getJsonResponse(Magazine $magazine): JsonResponse
    {
        return new JsonResponse(
            [
                'html' => $this->renderView(
                    'components/_ajax.html.twig',
                    [
                        'component' => 'magazine_sub',
                        'attributes' => [
                            'magazine' => $magazine,
                        ],
                    ]
                ),
            ]
        );
    }
}
