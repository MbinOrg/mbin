<?php

declare(strict_types=1);

namespace App\Controller\Tag;

use App\Controller\AbstractController;
use App\Entity\Hashtag;
use App\Service\TagManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TagSubscriptionController extends AbstractController
{
    public function __construct(
        private readonly TagManager $manager,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function subscribe(#[MapEntity(mapping: ['name' => 'tag'])] Hashtag $tag, Request $request): Response
    {
        $this->manager->subscribe($this->getUserOrThrow(), $tag);

        if ($request->isXmlHttpRequest()) {
            return $this->getJsonResponse($tag);
        }

        return $this->redirectToRefererOrHome($request);
    }

    #[IsGranted('ROLE_USER')]
    public function unsubscribe(#[MapEntity(mapping: ['name' => 'tag'])] Hashtag $tag, Request $request): Response
    {
        $this->manager->unsubscribe($this->getUserOrThrow(), $tag);

        if ($request->isXmlHttpRequest()) {
            return $this->getJsonResponse($tag);
        }

        return $this->redirectToRefererOrHome($request);
    }

    private function getJsonResponse(Hashtag $tag): JsonResponse
    {
        return new JsonResponse(
            [
                'html' => $this->renderView(
                    'components/_ajax.html.twig',
                    [
                        'component' => 'hashtag_sub',
                        'attributes' => [
                            'hashtag' => $tag,
                        ],
                    ]
                ),
            ]
        );
    }
}
