<?php

declare(strict_types=1);

namespace App\Controller\Api\Combined;

use App\Controller\Api\BaseApi;
use App\Controller\Traits\PrivateContentTrait;
use App\DTO\ContentResponseDto;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\PageView\ContentPageView;
use App\Pagination\Cursor\CursorPaginationInterface;
use App\Repository\ContentRepository;
use App\Repository\Criteria;
use App\Schema\CursorPaginationSchema;
use App\Schema\Errors\TooManyRequestsErrorSchema;
use App\Schema\Errors\UnauthorizedErrorSchema;
use App\Schema\PaginationSchema;
use App\Utils\SqlHelpers;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Pagerfanta\PagerfantaInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
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
    #[OA\Parameter(
        name: 'includeBoosts',
        description: 'if true then boosted content from followed users are included',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false)
    )]
    #[OA\Tag(name: 'combined')]
    public function collection(
        RateLimiterFactoryInterface $apiReadLimiter,
        RateLimiterFactoryInterface $anonymousApiReadLimiter,
        Security $security,
        ContentRepository $contentRepository,
        SqlHelpers $sqlHelpers,
        #[MapQueryParameter] ?int $p,
        #[MapQueryParameter] ?int $perPage,
        #[MapQueryParameter] ?string $sort,
        #[MapQueryParameter] ?string $time,
        #[MapQueryParameter] ?string $federation,
        #[MapQueryParameter] ?bool $includeBoosts,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);
        $criteria = $this->getCriteria($p, $security, $sort, $time, $federation, $includeBoosts, $perPage, $sqlHelpers, null);

        $content = $contentRepository->findByCriteria($criteria);

        return $this->serializeContent($content, $headers);
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
        name: 'collectionType',
        description: 'the type of collection to get',
        in: 'path',
        schema: new OA\Schema(type: 'string', enum: ['subscribed', 'moderated', 'favourited'])
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
    #[OA\Parameter(
        name: 'includeBoosts',
        description: 'if true then boosted content from followed users are included',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false)
    )]
    #[OA\Tag(name: 'combined')]
    #[\Nelmio\ApiDocBundle\Attribute\Security(name: 'oauth2', scopes: ['read'])]
    #[IsGranted('ROLE_OAUTH2_READ')]
    public function userCollection(
        RateLimiterFactoryInterface $apiReadLimiter,
        RateLimiterFactoryInterface $anonymousApiReadLimiter,
        Security $security,
        ContentRepository $contentRepository,
        SqlHelpers $sqlHelpers,
        #[MapQueryParameter] ?int $p,
        #[MapQueryParameter] ?int $perPage,
        #[MapQueryParameter] ?string $sort,
        #[MapQueryParameter] ?string $time,
        #[MapQueryParameter] ?string $federation,
        #[MapQueryParameter] ?bool $includeBoosts,
        string $collectionType,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);
        $criteria = $this->getCriteria($p, $security, $sort, $time, $federation, $includeBoosts, $perPage, $sqlHelpers, $collectionType);

        $content = $contentRepository->findByCriteria($criteria);

        return $this->serializeContent($content, $headers);
    }

    #[OA\Response(
        response: 200,
        description: 'A cursor paginated list of combined entries and posts filtered by the query parameters',
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
                    ref: new Model(type: CursorPaginationSchema::class)
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
        name: 'cursor',
        description: 'The cursor',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: null)
    )]
    #[OA\Parameter(
        name: 'cursor2',
        description: 'The secondary cursor, always a datetime',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: null)
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
    #[OA\Parameter(
        name: 'includeBoosts',
        description: 'if true then boosted content from followed users are included',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false)
    )]
    #[OA\Tag(name: 'combined')]
    public function cursorCollection(
        RateLimiterFactoryInterface $apiReadLimiter,
        RateLimiterFactoryInterface $anonymousApiReadLimiter,
        Security $security,
        ContentRepository $contentRepository,
        #[MapQueryParameter] ?string $cursor,
        #[MapQueryParameter] ?string $cursor2,
        #[MapQueryParameter] ?int $perPage,
        #[MapQueryParameter] ?string $sort,
        #[MapQueryParameter] ?string $time,
        #[MapQueryParameter] ?string $federation,
        #[MapQueryParameter] ?bool $includeBoosts,
        SqlHelpers $sqlHelpers,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);
        $criteria = $this->getCriteria(1, $security, $sort, $time, $federation, $includeBoosts, $perPage, $sqlHelpers, null);
        $currentCursor = $this->getCursor($contentRepository, $criteria->sortOption, $cursor);
        $currentCursor2 = $cursor2 ? $this->getCursor($contentRepository, Criteria::SORT_NEW, $cursor2) : null;

        $content = $contentRepository->findByCriteriaCursored($criteria, $currentCursor, $currentCursor2);

        return $this->serializeContentCursored($content, $headers);
    }

    #[OA\Response(
        response: 200,
        description: 'A cursor paginated list of combined entries and posts from subscribed magazines and users filtered by the query parameters',
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
                    ref: new Model(type: CursorPaginationSchema::class)
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
        name: 'cursor',
        description: 'The cursor',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: null)
    )]
    #[OA\Parameter(
        name: 'cursor2',
        description: 'The secondary cursor, always a datetime',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: null)
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
    #[OA\Parameter(
        name: 'includeBoosts',
        description: 'if true then boosted content from followed users are included',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false)
    )]
    #[OA\Tag(name: 'combined')]
    #[\Nelmio\ApiDocBundle\Attribute\Security(name: 'oauth2', scopes: ['read'])]
    #[IsGranted('ROLE_OAUTH2_READ')]
    public function cursorUserCollection(
        RateLimiterFactoryInterface $apiReadLimiter,
        RateLimiterFactoryInterface $anonymousApiReadLimiter,
        Security $security,
        ContentRepository $contentRepository,
        #[MapQueryParameter] ?string $cursor,
        #[MapQueryParameter] ?string $cursor2,
        #[MapQueryParameter] ?int $perPage,
        #[MapQueryParameter] ?string $sort,
        #[MapQueryParameter] ?string $time,
        #[MapQueryParameter] ?string $federation,
        #[MapQueryParameter] ?bool $includeBoosts,
        string $collectionType,
        SqlHelpers $sqlHelpers,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);
        $criteria = $this->getCriteria(1, $security, $sort, $time, $federation, $includeBoosts, $perPage, $sqlHelpers, $collectionType);
        $currentCursor = $this->getCursor($contentRepository, $criteria->sortOption, $cursor);
        $currentCursor2 = $cursor2 ? $this->getCursor($contentRepository, Criteria::SORT_NEW, $cursor2) : null;

        $content = $contentRepository->findByCriteriaCursored($criteria, $currentCursor, $currentCursor2);

        return $this->serializeContentCursored($content, $headers);
    }

    private function getCriteria(?int $p, Security $security, ?string $sort, ?string $time, ?string $federation, ?bool $includeBoosts, ?int $perPage, SqlHelpers $sqlHelpers, ?string $collectionType): ContentPageView
    {
        $criteria = new ContentPageView($p ?? 1, $security);
        $criteria->sortOption = $sort ?? Criteria::SORT_HOT;
        $criteria->time = $criteria->resolveTime($time ?? Criteria::TIME_ALL);
        $criteria->setFederation($federation ?? Criteria::AP_ALL);
        $this->handleLanguageCriteria($criteria);
        $criteria->content = Criteria::CONTENT_COMBINED;
        $criteria->perPage = $perPage;
        $user = $security->getUser();
        if ($user instanceof User) {
            $criteria->includeBoosts = Criteria::SORT_NEW === $criteria->sortOption && ($includeBoosts ?? $user->showBoostsOfFollowing);
            $criteria->fetchCachedItems($sqlHelpers, $user);
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

        return $criteria;
    }

    private function serializeContent(PagerfantaInterface $content, array $headers): JsonResponse
    {
        $result = [];
        foreach ($content as $item) {
            if ($item instanceof Entry) {
                $this->handlePrivateContent($item);
                $result[] = new ContentResponseDto(entry: $this->serializeEntry($this->entryFactory->createDto($item), $this->tagLinkRepository->getTagsOfContent($item)));
            } elseif ($item instanceof Post) {
                $this->handlePrivateContent($item);
                $result[] = new ContentResponseDto(post: $this->serializePost($this->postFactory->createDto($item), $this->tagLinkRepository->getTagsOfContent($item)));
            } elseif ($item instanceof EntryComment) {
                $this->handlePrivateContent($item);
                $result[] = new ContentResponseDto(entryComment: $this->serializeEntryComment($this->entryCommentFactory->createDto($item), $this->tagLinkRepository->getTagsOfContent($item)));
            } elseif ($item instanceof PostComment) {
                $this->handlePrivateContent($item);
                $result[] = new ContentResponseDto(postComment: $this->serializePostComment($this->postCommentFactory->createDto($item), $this->tagLinkRepository->getTagsOfContent($item)));
            }
        }

        return new JsonResponse($this->serializePaginated($result, $content), headers: $headers);
    }

    private function serializeContentCursored(CursorPaginationInterface $content, array $headers): JsonResponse
    {
        $result = [];
        foreach ($content as $item) {
            if ($item instanceof Entry) {
                $this->handlePrivateContent($item);
                $result[] = new ContentResponseDto(entry: $this->serializeEntry($this->entryFactory->createDto($item), $this->tagLinkRepository->getTagsOfContent($item)));
            } elseif ($item instanceof Post) {
                $this->handlePrivateContent($item);
                $result[] = new ContentResponseDto(post: $this->serializePost($this->postFactory->createDto($item), $this->tagLinkRepository->getTagsOfContent($item)));
            }
        }

        return new JsonResponse($this->serializeCursorPaginated($result, $content), headers: $headers);
    }

    private function getCursor(ContentRepository $contentRepository, string $sortOption, ?string $cursor): int|\DateTime|\DateTimeImmutable
    {
        $initialCursor = $contentRepository->guessInitialCursor($sortOption);
        if ($initialCursor instanceof \DateTime || $initialCursor instanceof \DateTimeImmutable) {
            try {
                $currentCursor = null !== $cursor ? new \DateTimeImmutable($cursor) : $initialCursor;
            } catch (\DateException) {
                throw new BadRequestHttpException('The cursor is not a parsable datetime.');
            }
        } elseif (\is_int($initialCursor)) {
            $currentCursor = null !== $cursor ? \intval($cursor) : $initialCursor;
        } else {
            $this->logger->critical('Could not get a cursor from class "{c}"', ['c' => \get_class($initialCursor)]);
            throw new HttpException(500, 'Could not determine the cursor.');
        }

        return $currentCursor;
    }
}
