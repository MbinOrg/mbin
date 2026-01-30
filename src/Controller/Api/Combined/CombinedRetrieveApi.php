<?php

declare(strict_types=1);

namespace App\Controller\Api\Combined;

use App\Controller\Api\BaseApi;
use App\Controller\Traits\PrivateContentTrait;
use App\DTO\ContentResponseDto;
use App\Entity\Entry;
use App\Entity\Post;
use App\Entity\User;
use App\PageView\ContentPageView;
use App\Repository\ContentRepository;
use App\Repository\Criteria;
use App\Schema\Errors\TooManyRequestsErrorSchema;
use App\Schema\Errors\UnauthorizedErrorSchema;
use App\Schema\PaginationSchema;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CombinedRetrieveApi extends BaseApi
{
    use PrivateContentTrait;

    #[OA\Response(
        response: 200,
        description: 'A paginated list of combined entries and posts filtered by the query parameters',
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
                    items: new OA\Items(ref: new Model(type: ContentResponseDto::class))
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
    #[OA\Parameter(
        name: 'perPage',
        description: 'Number of content items to retrieve per page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: ContentRepository::PER_PAGE, maximum: self::MAX_PER_PAGE, minimum: self::MIN_PER_PAGE)
    )]
    #[OA\Parameter(
        name: 'sort',
        description: 'Sort method to use when retrieving content',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: Criteria::SORT_HOT, enum: Criteria::SORT_OPTIONS)
    )]
    #[OA\Parameter(
        name: 'time',
        description: 'Max age of retrieved content',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: Criteria::TIME_ALL, enum: Criteria::TIME_ROUTES_EN)
    )]
    #[OA\Parameter(
        name: 'lang[]',
        description: 'Language(s) of content to return',
        in: 'query',
        schema: new OA\Schema(
            type: 'array',
            items: new OA\Items(type: 'string', default: null, maxLength: 3, minLength: 2)
        ),
        explode: true,
        allowReserved: true
    )]
    #[OA\Parameter(
        name: 'usePreferredLangs',
        description: 'Filter by a user\'s preferred languages? (Requires authentication and takes precedence over lang[])',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false),
    )]
    #[OA\Parameter(
        name: 'federation',
        description: 'What type of federated entries to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: Criteria::AP_ALL, enum: Criteria::AP_OPTIONS)
    )]
    #[OA\Tag(name: 'combined')]
    public function collection(
        RateLimiterFactory $apiReadLimiter,
        RateLimiterFactory $anonymousApiReadLimiter,
        Security $security,
        ContentRepository $contentRepository,
        #[MapQueryParameter] ?int $p,
        #[MapQueryParameter] ?int $perPage,
        #[MapQueryParameter] ?string $sort,
        #[MapQueryParameter] ?string $time,
        #[MapQueryParameter] ?string $federation,
    ): JsonResponse {
        return $this->generateResponse($apiReadLimiter, $anonymousApiReadLimiter, $p, $security, $sort, $time, $federation, $perPage, $contentRepository);
    }

    #[OA\Response(
        response: 200,
        description: 'A paginated list of combined entries and posts from subscribed magazines and users filtered by the query parameters',
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
                    items: new OA\Items(ref: new Model(type: ContentResponseDto::class))
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
    #[OA\Parameter(
        name: 'perPage',
        description: 'Number of content items to retrieve per page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: ContentRepository::PER_PAGE, maximum: self::MAX_PER_PAGE, minimum: self::MIN_PER_PAGE)
    )]
    #[OA\Parameter(
        name: 'sort',
        description: 'Sort method to use when retrieving content',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: Criteria::SORT_HOT, enum: Criteria::SORT_OPTIONS)
    )]
    #[OA\Parameter(
        name: 'time',
        description: 'Max age of retrieved content',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: Criteria::TIME_ALL, enum: Criteria::TIME_ROUTES_EN)
    )]
    #[OA\Parameter(
        name: 'lang[]',
        description: 'Language(s) of content to return',
        in: 'query',
        schema: new OA\Schema(
            type: 'array',
            items: new OA\Items(type: 'string', default: null, maxLength: 3, minLength: 2)
        ),
        explode: true,
        allowReserved: true
    )]
    #[OA\Parameter(
        name: 'usePreferredLangs',
        description: 'Filter by a user\'s preferred languages? (Requires authentication and takes precedence over lang[])',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false),
    )]
    #[OA\Parameter(
        name: 'federation',
        description: 'What type of federated entries to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: Criteria::AP_ALL, enum: Criteria::AP_OPTIONS)
    )]
    #[OA\Tag(name: 'combined')]
    #[\Nelmio\ApiDocBundle\Attribute\Security(name: 'oauth2', scopes: ['read'])]
    #[IsGranted('ROLE_OAUTH2_READ')]
    public function userCollection(
        RateLimiterFactory $apiReadLimiter,
        RateLimiterFactory $anonymousApiReadLimiter,
        Security $security,
        ContentRepository $contentRepository,
        #[MapQueryParameter] ?int $p,
        #[MapQueryParameter] ?int $perPage,
        #[MapQueryParameter] ?string $sort,
        #[MapQueryParameter] ?string $time,
        #[MapQueryParameter] ?string $federation,
        string $collectionType,
    ): JsonResponse {
        return $this->generateResponse($apiReadLimiter, $anonymousApiReadLimiter, $p, $security, $sort, $time, $federation, $perPage, $contentRepository, $collectionType);
    }

    private function generateResponse(
        RateLimiterFactory $apiReadLimiter,
        RateLimiterFactory $anonymousApiReadLimiter,
        ?int $p,
        Security $security,
        ?string $sort,
        ?string $time,
        ?string $federation,
        ?int $perPage,
        ContentRepository $contentRepository,
        ?string $collectionType = null,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);
        $criteria = new ContentPageView($p ?? 1, $security);
        $criteria->sortOption = $sort ?? Criteria::SORT_HOT;
        $criteria->time = $criteria->resolveTime($time ?? Criteria::TIME_ALL);
        $criteria->setFederation($federation ?? Criteria::AP_ALL);
        $this->handleLanguageCriteria($criteria);
        $criteria->content = Criteria::CONTENT_THREADS;
        $criteria->perPage = $perPage;
        $user = $security->getUser();
        if ($user instanceof User) {
            $criteria->fetchCachedItems($contentRepository, $user);
        }

        switch ($collectionType) {
            case 'subscribed':
                $criteria->subscribed = true;
                break;
            case 'moderated':
                $criteria->moderated = true;
                break;
            case 'favourited':
                $criteria->favourite = true;
                break;
        }

        $content = $contentRepository->findByCriteria($criteria);
        $this->handleLanguageCriteria($criteria);

        $result = [];
        foreach ($content->getCurrentPageResults() as $item) {
            if ($item instanceof Entry) {
                $this->handlePrivateContent($item);
                $result[] = new ContentResponseDto(entry: $this->serializeEntry($this->entryFactory->createDto($item), $this->tagLinkRepository->getTagsOfContent($item)));
            } elseif ($item instanceof Post) {
                $this->handlePrivateContent($item);
                $result[] = new ContentResponseDto(post: $this->serializePost($this->postFactory->createDto($item), $this->tagLinkRepository->getTagsOfContent($item)));
            }
        }

        return new JsonResponse($this->serializePaginated($result, $content), headers: $headers);
    }
}
