<?php

declare(strict_types=1);

namespace App\Controller\Api\User;

use App\DTO\MagazineSmallResponseDto;
use App\Entity\User;
use App\Repository\MagazineRepository;
use App\Schema\Errors\TooManyRequestsErrorSchema;
use App\Schema\Errors\UnauthorizedErrorSchema;
use App\Schema\PaginationSchema;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class UserModeratesApi extends UserBaseApi
{
    #[OA\Response(
        response: 200,
        description: 'A paginated list of magazines which are moderated by the given user',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: MagazineSmallResponseDto::class))
                ),
                new OA\Property(
                    property: 'pagination',
                    ref: new Model(type: PaginationSchema::class)
                ),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'user not found or you are not allowed to access them',
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
    #[OA\Parameter(
        name: 'p',
        description: 'Page of content to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
    )]
    #[OA\Tag(name: 'user')]
    public function __invoke(
        #[MapEntity(id: 'user_id')]
        User $user,
        #[MapQueryParameter]
        ?int $p,
        MagazineRepository $repository,
        RateLimiterFactory $apiReadLimiter,
        RateLimiterFactory $anonymousApiReadLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $requestingUser = $this->getUser();
        if ($user->isDeleted && (!$requestingUser || (!$requestingUser->isAdmin() && !$requestingUser->isModerator()) || null === $user->markedForDeletionAt)) {
            throw $this->createNotFoundException();
        }

        $magazines = $repository->findModeratedMagazines($user, $p ?? 1);

        $result = [];
        foreach ($magazines as $magazine) {
            $result[] = $this->magazineFactory->createSmallDto($magazine);
        }

        return new JsonResponse(
            $this->serializePaginated($result, $magazines),
            headers: $headers
        );
    }
}
