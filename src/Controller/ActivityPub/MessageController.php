<?php

declare(strict_types=1);

namespace App\Controller\ActivityPub;

use App\Controller\AbstractController;
use App\Entity\Message;
use App\Factory\ActivityPub\MessageFactory;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MessageController extends AbstractController
{
    public function __construct(
        private readonly MessageFactory $factory,
    ) {
    }

    public function __invoke(
        #[MapEntity]
        Message $message,
        Request $request,
    ): Response {
        $json = $this->factory->build($message);

        $response = new JsonResponse($json);
        $response->headers->set('Content-Type', 'application/activity+json');

        return $response;
    }
}
