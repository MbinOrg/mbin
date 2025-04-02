<?php

declare(strict_types=1);

namespace App\Controller\Api\Post;

use App\Controller\Traits\PrivateContentTrait;
use App\DTO\ReportRequestDto;
use App\Entity\Post;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PostsReportApi extends PostsBaseApi
{
    use PrivateContentTrait;

    #[OA\Response(
        response: 204,
        description: 'Report created',
        content: null,
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
        description: 'You have not been authorized to report this post',
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
        description: 'The post to report',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(content: new Model(type: ReportRequestDto::class))]
    #[OA\Tag(name: 'post')]
    #[Security(name: 'oauth2', scopes: ['post:report'])]
    #[IsGranted('ROLE_OAUTH2_POST:REPORT')]
    public function __invoke(
        #[MapEntity(id: 'post_id')]
        Post $post,
        RateLimiterFactory $apiReportLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReportLimiter);

        $this->reportContent($post);

        return new JsonResponse(
            status: 204,
            headers: $headers
        );
    }
}
