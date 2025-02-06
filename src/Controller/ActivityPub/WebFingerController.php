<?php

declare(strict_types=1);

namespace App\Controller\ActivityPub;

use App\ActivityPub\JsonRd;
use App\Event\ActivityPub\WebfingerResponseEvent;
use App\Service\ActivityPub\Webfinger\WebFingerParameters;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class WebFingerController
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly WebFingerParameters $webFingerParameters,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $event = new WebfingerResponseEvent(
            new JsonRd(),
            $request->query->get('resource') ?: '',
            $this->webFingerParameters->getParams($request),
        );
        $this->eventDispatcher->dispatch($event);

        if (!empty($event->jsonRd->getLinks())) {
            $response = new JsonResponse($event->jsonRd->toArray());
        } else {
            $response = new JsonResponse();
            $response->setStatusCode(404);
            $response->headers->set('Status', '404 Not Found');
        }

        $response->headers->set('Content-Type', 'application/jrd+json; charset=utf-8');
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }
}
