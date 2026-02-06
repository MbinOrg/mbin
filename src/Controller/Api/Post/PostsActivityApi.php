<?php

declare(strict_types=1);

namespace App\Controller\Api\Post;

use App\Controller\Traits\PrivateContentTrait;
use App\DTO\ActivitiesResponseDto;
use App\Entity\Post;
use App\Factory\ContentActivityDtoFactory;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class PostsActivityApi extends PostsBaseApi
{
    use PrivateContentTrait;

    #[OA\Response(
        response: 200,
        description: 'Vote activity of the post',
        content: new Model(type: ActivitiesResponseDto::class),
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Response(
        response: 403,
        description: 'You are not authorized to access this post',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\ForbiddenErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Post not found',
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
        name: 'post_id',
        in: 'path',
        description: 'The post to query',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Tag(name: 'post')]
    public function __invoke(
        #[MapEntity(id: 'post_id')]
        Post $post,
        ContentActivityDtoFactory $dtoFactory,
        RateLimiterFactory $apiReadLimiter,
        RateLimiterFactory $anonymousApiReadLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $this->handlePrivateContent($post);

        $dto = $dtoFactory->createActivitiesDto($post);

        return new JsonResponse(
            $dto,
            headers: $headers
        );
    }
}
