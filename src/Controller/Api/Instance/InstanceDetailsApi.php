<?php

declare(strict_types=1);

namespace App\Controller\Api\Instance;

use App\DTO\RemoteInstanceDto;
use App\DTO\SiteResponseDto;
use App\Entity\Instance;
use App\Repository\SiteRepository;
use App\Schema\Errors\ForbiddenErrorSchema;
use App\Schema\Errors\NotFoundErrorSchema;
use App\Schema\Errors\TooManyRequestsErrorSchema;
use App\Schema\Errors\UnauthorizedErrorSchema;
use App\Service\SettingsManager;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class InstanceDetailsApi extends InstanceBaseApi
{
    #[OA\Response(
        response: 200,
        description: 'Returns the site\'s details',
        content: new OA\JsonContent(ref: new Model(type: SiteResponseDto::class)),
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 429,
        description: 'You are being rate limited',
        content: new OA\JsonContent(ref: new Model(type: TooManyRequestsErrorSchema::class)),
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Tag('instance')]
    /**
     * Retrieve information about the instance written by the admin.
     */
    public function __invoke(
        SiteRepository $repository,
        RateLimiterFactory $apiReadLimiter,
        RateLimiterFactory $anonymousApiReadLimiter,
        SettingsManager $settingsManager,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $results = $repository->findAll();
        $dto = new SiteResponseDto(null, $settingsManager->getDownvotesMode());
        if (0 < \count($results)) {
            $dto = new SiteResponseDto($results[0], $settingsManager->getDownvotesMode());
        }

        return new JsonResponse(
            $dto,
            headers: $headers
        );
    }

    #[OA\Response(
        response: 200,
        description: 'Returns the details of a remote instance',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new OA\JsonContent(ref: new Model(type: RemoteInstanceDto::class))
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 403,
        description: 'You do not have permission to view the details for remote instances',
        content: new OA\JsonContent(ref: new Model(type: ForbiddenErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Remote instance not found',
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
    #[IsGranted('ROLE_ADMIN')]
    #[Security(name: 'oauth2', scopes: ['admin:federation:read'])]
    #[IsGranted('ROLE_OAUTH2_ADMIN:FEDERATION:READ')]
    #[OA\Tag('admin/instance')]
    public function retrieveRemoteInstanceDetails(
        RateLimiterFactory $apiReadLimiter,
        #[MapEntity] Instance $instance,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter);

        $dto = RemoteInstanceDto::create($instance, $this->instanceRepository->getInstanceCounts($instance));

        return new JsonResponse($dto, headers: $headers);
    }
}
