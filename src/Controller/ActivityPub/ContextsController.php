<?php

declare(strict_types=1);

namespace App\Controller\ActivityPub;

use App\Service\ActivityPub\ContextsProvider;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ContextsController
{
    public function __invoke(Request $request, ContextsProvider $context): JsonResponse
    {
        return new JsonResponse(
            ['@context' => $context->embeddedContexts()],
            200,
            [
                'Content-Type' => 'application/ld+json',
                'Access-Control-Allow-Origin' => '*',
            ]
        );
    }
}
