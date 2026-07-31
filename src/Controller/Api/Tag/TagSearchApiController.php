<?php
declare(strict_types=1);

namespace App\Controller\Api\Tag;

use App\DTO\HashtagResponseDto;
use App\Entity\Hashtag;
use App\Repository\SearchRepository;
use App\Schema\PaginationSchema;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

class TagSearchApiController extends TagBaseApi
{
    #[OA\Response(
        response: 200,
        description: 'Returns a paginated list of hashtags.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: HashtagResponseDto::class))
                ),
                new OA\Property(
                    property: 'pagination',
                    ref: new Model(type: PaginationSchema::class)
                ),
            ]
        ),
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
        name: 'p',
        description: 'Page of items to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
    )]
    #[OA\Parameter(
        name: 'perPage',
        description: 'Number of items per page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: SearchRepository::PER_PAGE, minimum: self::MIN_PER_PAGE, maximum: self::MAX_PER_PAGE)
    )]
    #[OA\Tag(name: 'tag')]
    public function listAll(
        RateLimiterFactoryInterface $apiReadLimiter,
        RateLimiterFactoryInterface $anonymousApiReadLimiter,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        int $perPage = SearchRepository::PER_PAGE,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $request = $this->request->getCurrentRequest();
        $page = $this->getPageNb($request);
        $perPage = self::constrainPerPage($perPage);

        $hashtags = $this->tagRepository->findAllPaginated($page, $perPage);

        $dtos = [];
        foreach ($hashtags->getCurrentPageResults() as $value) {
            \assert($value instanceof Hashtag);
            $dtos[] = $this->serializeHashtag($value);
        }

        return new JsonResponse(
            $this->serializePaginated($dtos, $hashtags),
            headers: $headers
        );
    }

    #[OA\Response(
        response: 200,
        description: 'Returns a paginated list of hashtags.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: HashtagResponseDto::class))
                ),
                new OA\Property(
                    property: 'pagination',
                    ref: new Model(type: PaginationSchema::class)
                ),
            ]
        ),
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Response(
        response: 400,
        description: 'The search query parameter `q` is required!',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\BadRequestErrorSchema::class))
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\UnauthorizedErrorSchema::class))
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
        name: 'p',
        description: 'Page of items to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
    )]
    #[OA\Parameter(
        name: 'perPage',
        description: 'Number of items per page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: SearchRepository::PER_PAGE, minimum: self::MIN_PER_PAGE, maximum: self::MAX_PER_PAGE)
    )]
    #[OA\Parameter(
        name: 'q',
        description: 'Search term',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Tag(name: 'tag')]
    public function search(
        RateLimiterFactoryInterface $apiReadLimiter,
        RateLimiterFactoryInterface $anonymousApiReadLimiter,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        string $q,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        int $perPage = SearchRepository::PER_PAGE,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $request = $this->request->getCurrentRequest();
        $page = $this->getPageNb($request);
        $perPage = self::constrainPerPage($perPage);

        $hashtags = $this->tagManager->search($q, $page, $perPage);

        $dtos = [];
        foreach ($hashtags->getCurrentPageResults() as $value) {
            \assert($value instanceof Hashtag);
            $dtos[] = $this->serializeHashtag($value);
        }

        return new JsonResponse(
            $this->serializePaginated($dtos, $hashtags),
            headers: $headers
        );
    }
}
