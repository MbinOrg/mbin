<?php

declare(strict_types=1);

namespace App\Controller\Api\Message;

use App\Controller\Traits\PrivateContentTrait;
use App\DTO\MessageDto;
use App\DTO\MessageThreadResponseDto;
use App\Entity\MessageThread;
use App\Service\MessageManager;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MessageThreadReplyApi extends MessageBaseApi
{
    use PrivateContentTrait;

    #[OA\Response(
        response: 201,
        description: 'Message reply added',
        content: new Model(type: MessageThreadResponseDto::class),
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Response(
        response: 400,
        description: 'The request body was invalid',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\BadRequestErrorSchema::class))
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 403,
        description: 'You are not permitted to message in thread',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\ForbiddenErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'User not found',
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
        in: 'path',
        description: 'Thread being replied to',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'd',
        in: 'query',
        description: 'Number of replies returned',
        schema: new OA\Schema(type: 'integer', default: self::REPLY_DEPTH, minimum: self::MIN_REPLY_DEPTH, maximum: self::MAX_REPLY_DEPTH)
    )]
    #[OA\RequestBody(content: new Model(type: MessageDto::class))]
    #[OA\Tag(name: 'message')]
    #[Security(name: 'oauth2', scopes: ['user:message:create'])]
    #[IsGranted('ROLE_OAUTH2_USER:MESSAGE:CREATE')]
    #[IsGranted('show', subject: 'thread', statusCode: 403)]
    public function __invoke(
        #[MapEntity(id: 'thread_id')]
        MessageThread $thread,
        MessageManager $manager,
        ValidatorInterface $validator,
        RateLimiterFactory $apiMessageLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiMessageLimiter);

        $dto = $this->deserializeMessage();

        $errors = $validator->validate($dto);
        if (\count($errors) > 0) {
            throw new BadRequestHttpException((string) $errors);
        }

        $manager->toMessage($dto, $thread, $this->getUserOrThrow());

        return new JsonResponse(
            $this->serializeMessageThread($thread),
            status: 201,
            headers: $headers
        );
    }
}
