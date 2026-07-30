<?php

declare(strict_types=1);

namespace App\Controller\Api\Tag;

use App\DTO\DomainDto;
use App\DTO\HashtagResponseDto;
use App\Entity\Hashtag;
use App\Entity\HashtagBlock;
use App\Repository\TagRepository;
use App\Schema\PaginationSchema;
use App\Service\TagManager;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TagBlockApiController extends TagBaseApi
{
    #[OA\Response(
        response: 200,
        description: 'Hashtag blocked',
        content: new Model(type: HashtagResponseDto::class),
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
        response: 404,
        description: 'Hashtag not found',
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
        name: 'name',
        in: 'path',
        description: 'The hashtag to block',
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Tag(name: 'tag')]
    #[Security(name: 'oauth2', scopes: ['hashtag:block'])]
    #[IsGranted('ROLE_OAUTH2_HASHTAG:BLOCK')]
    public function block(
        #[MapEntity(mapping: ['name' => 'tag'])]
        Hashtag $tag,
        TagManager $manager,
        RateLimiterFactoryInterface $apiUpdateLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiUpdateLimiter);

        $manager->block($this->getUserOrThrow(), $tag);

        return new JsonResponse(
            $this->serializeHashtag($tag),
            headers: $headers
        );
    }

    #[OA\Response(
        response: 200,
        description: 'Hashtag unblocked',
        content: new Model(type: DomainDto::class),
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
        response: 404,
        description: 'Hashtag not found',
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
        name: 'name',
        in: 'path',
        description: 'The hashtag to unblock',
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Tag(name: 'tag')]
    #[Security(name: 'oauth2', scopes: ['hashtag:block'])]
    #[IsGranted('ROLE_OAUTH2_HASHTAG:BLOCK')]
    public function unblock(
        #[MapEntity(mapping: ['name' => 'tag'])]
        Hashtag $tag,
        TagManager $manager,
        RateLimiterFactoryInterface $apiUpdateLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiUpdateLimiter);

        $manager->unblock($this->getUserOrThrow(), $tag);

        return new JsonResponse(
            $this->serializeHashtag($tag),
            headers: $headers
        );
    }

    #[OA\Response(
        response: 200,
        description: 'Returns a paginated list of blocked hashtags',
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
        description: 'Page of hashtags to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
    )]
    #[OA\Parameter(
        name: 'perPage',
        description: 'Number of hashtags per page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: TagRepository::PER_PAGE, minimum: self::MIN_PER_PAGE, maximum: self::MAX_PER_PAGE)
    )]
    #[OA\Tag(name: 'tag')]
    #[Security(name: 'oauth2', scopes: ['hashtag:block'])]
    #[IsGranted('ROLE_OAUTH2_HASHTAG:BLOCK')]
    public function list(
        RateLimiterFactoryInterface $apiReadLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter);

        $request = $this->request->getCurrentRequest();
        $blocks = $this->tagRepository->findBlockedTags(
            $this->getPageNb($request),
            $this->getUserOrThrow(),
            self::constrainPerPage($request->get('perPage', TagRepository::PER_PAGE))
        );

        $dtos = [];
        foreach ($blocks->getCurrentPageResults() as $value) {
            \assert($value instanceof HashtagBlock);
            $dtos[] = $this->serializeHashtag($value->hashtag);
        }

        return new JsonResponse(
            $this->serializePaginated($dtos, $blocks),
            headers: $headers
        );
    }
}
