<?php

declare(strict_types=1);

namespace App\Controller\Api\Instance;

use App\DTO\InstanceDomainsRequestDto;
use App\DTO\InstanceDto;
use App\DTO\InstancesDtoV2;
use App\Entity\InstanceBlock;
use App\Schema\Errors\BadRequestErrorSchema;
use App\Schema\Errors\NotFoundErrorSchema;
use App\Schema\Errors\TooManyRequestsErrorSchema;
use App\Schema\Errors\UnauthorizedErrorSchema;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class InstanceUserBlockApi extends InstanceBaseApi
{
    #[OA\Response(
        response: 200,
        description: 'blocked Instance',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new Model(type: InstancesDtoV2::class)
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 429,
        description: 'You are being rate limited',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new OA\JsonContent(ref: new Model(type: TooManyRequestsErrorSchema::class))
    )]
    #[OA\Tag(name: 'user')]
    #[Security(name: 'oauth2', scopes: ['user:profile:edit'])]
    #[IsGranted('ROLE_OAUTH2_USER:PROFILE:EDIT')]
    public function retrieve(
        RateLimiterFactoryInterface $apiReadLimiter,
        RateLimiterFactoryInterface $anonymousApiReadLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);
        $user = $this->getUserOrThrow();

        $userInstanceBlocks = $this->instanceBlockRepository->findBlocksForUser($user);
        $instances = array_map(fn (InstanceBlock $b) => new InstanceDto($b->instance->domain, $b->instance->software, $b->instance->version), $userInstanceBlocks);
        $dto = new InstancesDtoV2($instances);

        return new JsonResponse(
            $dto,
            headers: $headers
        );
    }

    #[OA\RequestBody(content: new Model(
        type: InstanceDomainsRequestDto::class,
    ))]
    #[OA\Response(
        response: 204,
        description: 'Instance is blocked',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
    )]
    #[OA\Response(
        response: 400,
        description: 'Instance domain not set in request-body',
        content: new OA\JsonContent(ref: new Model(type: BadRequestErrorSchema::class))
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Instance with given domain was not found',
        content: new OA\JsonContent(ref: new Model(type: NotFoundErrorSchema::class))
    )]
    #[OA\Response(
        response: 429,
        description: 'You are being rate limited',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new OA\JsonContent(ref: new Model(type: TooManyRequestsErrorSchema::class))
    )]
    #[OA\Tag(name: 'user')]
    #[Security(name: 'oauth2', scopes: ['user:profile:edit'])]
    #[IsGranted('ROLE_OAUTH2_USER:PROFILE:EDIT')]
    public function block(
        RateLimiterFactoryInterface $apiUpdateLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiUpdateLimiter);
        $user = $this->getUserOrThrow();

        $instances = $this->getInstancesFromDomainsRequest();

        foreach ($instances as $instance) {
            $this->instanceManager->blockInstance($instance, $user);
        }

        return new JsonResponse(status: 204, headers: $headers);
    }

    #[OA\RequestBody(content: new Model(
        type: InstanceDomainsRequestDto::class,
    ))]
    #[OA\Response(
        response: 204,
        description: 'Instance is unblocked',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
    )]
    #[OA\Response(
        response: 400,
        description: 'Instance domain not set in request-body',
        content: new OA\JsonContent(ref: new Model(type: BadRequestErrorSchema::class))
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Instance with given domain was not found',
        content: new OA\JsonContent(ref: new Model(type: NotFoundErrorSchema::class))
    )]
    #[OA\Response(
        response: 429,
        description: 'You are being rate limited',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new OA\JsonContent(ref: new Model(type: TooManyRequestsErrorSchema::class))
    )]
    #[OA\Tag(name: 'user')]
    #[Security(name: 'oauth2', scopes: ['user:profile:edit'])]
    #[IsGranted('ROLE_OAUTH2_USER:PROFILE:EDIT')]
    public function unblock(
        RateLimiterFactoryInterface $apiUpdateLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiUpdateLimiter);
        $user = $this->getUserOrThrow();

        $instances = $this->getInstancesFromDomainsRequest();

        foreach ($instances as $instance) {
            $this->instanceManager->unblockInstance($instance, $user);
        }

        return new JsonResponse(status: 204, headers: $headers);
    }
}
