<?php

declare(strict_types=1);

namespace App\Controller\Api\Message;

use App\Controller\Traits\PrivateContentTrait;
use App\Entity\MessageThread;
use App\Service\MessageManager;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MessageRemoveApi extends MessageBaseApi
{
    use PrivateContentTrait;

    #[OA\Response(
        response: 204,
        description: 'The thread was deleted for the user',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 403,
        description: 'You are not allowed to view the messages in this thread',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\ForbiddenErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Page not found',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\NotFoundErrorSchema::class))
    )]
    #[OA\Response(
        response: 429,
        description: 'You are being rate limited',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\TooManyRequestsErrorSchema::class)),
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Parameter(
        name: 'thread_id',
        description: 'Thread from which to retrieve messages',
        in: 'path',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Tag(name: 'message')]
    #[Security(name: 'oauth2', scopes: ['user:message:read'])]
    #[IsGranted('ROLE_OAUTH2_USER:MESSAGE:DELETE')]
    #[IsGranted('show', subject: 'thread', statusCode: 403)]
    public function removeThread(
        #[MapEntity(id: 'thread_id')]
        MessageThread $thread,
        MessageManager $manager,
        RateLimiterFactoryInterface $apiReadLimiter,
    ): Response {
        $headers = $this->rateLimit($apiReadLimiter);

        $manager->removeUserFromThread($thread, $this->getUserOrThrow());

        return new Response(status: 204, headers: $headers);
    }
}
