<?php

declare(strict_types=1);

namespace App\Controller\Api\Tag;

use App\Controller\Api\BaseApi;
use App\Controller\Traits\PrivateContentTrait;
use App\DTO\EntryCommentResponseDto;
use App\DTO\EntryResponseDto;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Factory\EntryCommentFactory;
use App\Factory\EntryFactory;
use App\Factory\PostCommentFactory;
use App\Factory\PostFactory;
use App\PageView\EntryCommentPageView;
use App\PageView\EntryPageView;
use App\PageView\PostCommentPageView;
use App\PageView\PostPageView;
use App\Repository\Criteria;
use App\Repository\EntryCommentRepository;
use App\Repository\EntryRepository;
use App\Repository\PostCommentRepository;
use App\Repository\PostRepository;
use App\Schema\PaginationSchema;
use App\Service\TagExtractor;
use App\Utils\SqlHelpers;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class TagContentRetrieveApiController extends BaseApi
{
    use PrivateContentTrait;

    #[OA\Response(
        response: 200,
        description: 'Returns a paginated list of entries which have the specific hashtag',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: EntryResponseDto::class))
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
        name: 'name',
        in: 'path',
        description: 'The tag to search for',
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'sortBy',
        in: 'query',
        description: 'The sorting method to use during item fetch (if the user is logged-in the default from their settings will be used)',
        schema: new OA\Schema(
            type: 'string',
            default: EntryPageView::SORT_DEFAULT,
            enum: EntryPageView::SORT_OPTIONS
        )
    )]
    #[OA\Parameter(
        name: 'time',
        in: 'query',
        description: 'The maximum age of retrieved items',
        schema: new OA\Schema(
            type: 'string',
            default: Criteria::TIME_ALL,
            enum: Criteria::TIME_ROUTES_EN
        )
    )]
    #[OA\Parameter(
        name: 'type',
        in: 'query',
        description: 'The maximum age of retrieved entries',
        schema: new OA\Schema(
            type: 'string',
            default: 'all',
            enum: [...Entry::ENTRY_TYPE_OPTIONS, 'all']
        )
    )]
    #[OA\Parameter(
        name: 'federation',
        in: 'query',
        description: 'Whether to include federated content or not',
        schema: new OA\Schema(
            type: 'string',
            default: Criteria::AP_ALL,
            enum: Criteria::AP_OPTIONS
        )
    )]
    #[OA\Parameter(
        name: 'p',
        description: 'Page of items to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
    )]
    #[OA\Parameter(
        name: 'perPage',
        description: 'Number of items to retrieve per page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: EntryRepository::PER_PAGE, minimum: self::MIN_PER_PAGE, maximum: self::MAX_PER_PAGE)
    )]
    #[OA\Parameter(
        name: 'lang[]',
        description: 'Language(s) of entries to return',
        in: 'query',
        explode: true,
        allowReserved: true,
        schema: new OA\Schema(
            type: 'array',
            items: new OA\Items(type: 'string', default: null, minLength: 2, maxLength: 3)
        )
    )]
    #[OA\Parameter(
        name: 'usePreferredLangs',
        description: 'Filter by a user\'s preferred languages (Requires authentication and takes precedence over lang[])',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false),
    )]
    #[OA\Tag(name: 'tag')]
    public function entries(
        string $name,
        #[MapQueryParameter]
        ?string $sortBy,
        #[MapQueryParameter]
        ?string $time,
        #[MapQueryParameter]
        ?string $type,
        #[MapQueryParameter]
        ?string $federation,
        EntryRepository $entryRepository,
        EntryFactory $factory,
        TagExtractor $tagManager,
        SqlHelpers $sqlHelpers,
        Security $security,
        RateLimiterFactoryInterface $apiReadLimiter,
        RateLimiterFactoryInterface $anonymousApiReadLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);
        $request = $this->request->getCurrentRequest();

        $criteria = new EntryPageView($this->getPageNb($request), $security);
        $criteria->setTime($criteria->resolveTime($time ?? Criteria::TIME_ALL))
            ->setFederation($federation ?? Criteria::AP_ALL)
            ->setType($type ?? 'all')
            ->setTag($tagManager->transliterate(strtolower($name)));
        $this->setSort($criteria, $sortBy, $security);
        $this->handleLanguageCriteria($criteria);
        $criteria->content = Criteria::CONTENT_THREADS;
        $criteria->perPage = $this->getPerPage($request, EntryRepository::PER_PAGE);

        $user = $security->getUser();
        if ($user instanceof User) {
            $criteria->fetchCachedItems($sqlHelpers, $user);
        }

        $entries = $entryRepository->findByCriteria($criteria);

        $dtos = [];
        foreach ($entries->getCurrentPageResults() as $value) {
            try {
                \assert($value instanceof Entry);
                $this->handlePrivateContent($value);
                $dtos[] = $this->serializeEntry($factory->createDto($value), $this->tagLinkRepository->getTagsOfContent($value));
            } catch (AccessDeniedException $e) {
                continue;
            }
        }

        return new JsonResponse(
            $this->serializePaginated($dtos, $entries),
            headers: $headers
        );
    }

    #[OA\Response(
        response: 200,
        description: 'Returns a paginated list of entry-comments which have the specific hashtag',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: EntryCommentResponseDto::class))
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
        name: 'name',
        in: 'path',
        description: 'The tag to search for',
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'sortBy',
        in: 'query',
        description: 'The sorting method to use during item fetch (if the user is logged-in the default from their settings will be used)',
        schema: new OA\Schema(
            type: 'string',
            default: EntryPageView::SORT_DEFAULT,
            enum: EntryPageView::SORT_OPTIONS
        )
    )]
    #[OA\Parameter(
        name: 'time',
        in: 'query',
        description: 'The maximum age of retrieved items',
        schema: new OA\Schema(
            type: 'string',
            default: Criteria::TIME_ALL,
            enum: Criteria::TIME_ROUTES_EN
        )
    )]
    #[OA\Parameter(
        name: 'federation',
        in: 'query',
        description: 'Whether to include federated content or not',
        schema: new OA\Schema(
            type: 'string',
            default: Criteria::AP_ALL,
            enum: Criteria::AP_OPTIONS
        )
    )]
    #[OA\Parameter(
        name: 'p',
        description: 'Page of items to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
    )]
    #[OA\Parameter(
        name: 'perPage',
        description: 'Number of items to retrieve per page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: EntryRepository::PER_PAGE, minimum: self::MIN_PER_PAGE, maximum: self::MAX_PER_PAGE)
    )]
    #[OA\Parameter(
        name: 'lang[]',
        description: 'Language(s) of comments to return',
        in: 'query',
        explode: true,
        allowReserved: true,
        schema: new OA\Schema(
            type: 'array',
            items: new OA\Items(type: 'string', default: null, minLength: 2, maxLength: 3)
        )
    )]
    #[OA\Parameter(
        name: 'usePreferredLangs',
        description: 'Filter by a user\'s preferred languages (Requires authentication and takes precedence over lang[])',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false),
    )]
    #[OA\Tag(name: 'tag')]
    public function entryComments(
        string $name,
        #[MapQueryParameter]
        ?string $sortBy,
        #[MapQueryParameter]
        ?string $time,
        #[MapQueryParameter]
        ?string $federation,
        EntryCommentRepository $commentRepository,
        EntryCommentFactory $factory,
        TagExtractor $tagManager,
        SqlHelpers $sqlHelpers,
        Security $security,
        RateLimiterFactoryInterface $apiReadLimiter,
        RateLimiterFactoryInterface $anonymousApiReadLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);
        $request = $this->request->getCurrentRequest();

        $criteria = new EntryCommentPageView($this->getPageNb($request), $security);
        $criteria->setTime($criteria->resolveTime($time ?? Criteria::TIME_ALL))
            ->setFederation($federation ?? Criteria::AP_ALL)
            ->setTag($tagManager->transliterate(strtolower($name)));
        $this->setSort($criteria, $sortBy, $security);
        $this->handleLanguageCriteria($criteria);
        $criteria->perPage = $this->getPerPage($request, EntryCommentRepository::PER_PAGE);

        $user = $security->getUser();
        if ($user instanceof User) {
            $criteria->fetchCachedItems($sqlHelpers, $user);
        }

        $comments = $commentRepository->findByCriteria($criteria);

        $dtos = [];
        foreach ($comments->getCurrentPageResults() as $value) {
            try {
                \assert($value instanceof EntryComment);
                $this->handlePrivateContent($value);
                $dtos[] = $this->serializeEntryComment($factory->createDto($value), $this->tagLinkRepository->getTagsOfContent($value));
            } catch (AccessDeniedException $e) {
                continue;
            }
        }

        return new JsonResponse(
            $this->serializePaginated($dtos, $comments),
            headers: $headers
        );
    }

    #[OA\Response(
        response: 200,
        description: 'Returns a paginated list of posts which have the specific hashtag',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: EntryCommentResponseDto::class))
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
        name: 'name',
        in: 'path',
        description: 'The tag to search for',
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'sortBy',
        in: 'query',
        description: 'The sorting method to use during item fetch (if the user is logged-in the default from their settings will be used)',
        schema: new OA\Schema(
            type: 'string',
            default: EntryPageView::SORT_DEFAULT,
            enum: EntryPageView::SORT_OPTIONS
        )
    )]
    #[OA\Parameter(
        name: 'time',
        in: 'query',
        description: 'The maximum age of retrieved items',
        schema: new OA\Schema(
            type: 'string',
            default: Criteria::TIME_ALL,
            enum: Criteria::TIME_ROUTES_EN
        )
    )]
    #[OA\Parameter(
        name: 'federation',
        in: 'query',
        description: 'Whether to include federated content or not',
        schema: new OA\Schema(
            type: 'string',
            default: Criteria::AP_ALL,
            enum: Criteria::AP_OPTIONS
        )
    )]
    #[OA\Parameter(
        name: 'p',
        description: 'Page of items to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
    )]
    #[OA\Parameter(
        name: 'perPage',
        description: 'Number of items to retrieve per page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: EntryRepository::PER_PAGE, minimum: self::MIN_PER_PAGE, maximum: self::MAX_PER_PAGE)
    )]
    #[OA\Parameter(
        name: 'lang[]',
        description: 'Language(s) of posts to return',
        in: 'query',
        explode: true,
        allowReserved: true,
        schema: new OA\Schema(
            type: 'array',
            items: new OA\Items(type: 'string', default: null, minLength: 2, maxLength: 3)
        )
    )]
    #[OA\Parameter(
        name: 'usePreferredLangs',
        description: 'Filter by a user\'s preferred languages (Requires authentication and takes precedence over lang[])',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false),
    )]
    #[OA\Tag(name: 'tag')]
    public function posts(
        string $name,
        #[MapQueryParameter]
        ?string $sortBy,
        #[MapQueryParameter]
        ?string $time,
        #[MapQueryParameter]
        ?string $federation,
        PostRepository $postRepository,
        PostFactory $factory,
        TagExtractor $tagManager,
        SqlHelpers $sqlHelpers,
        Security $security,
        RateLimiterFactoryInterface $apiReadLimiter,
        RateLimiterFactoryInterface $anonymousApiReadLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);
        $request = $this->request->getCurrentRequest();

        $criteria = new PostPageView($this->getPageNb($request), $security);
        $criteria->setTime($criteria->resolveTime($time ?? Criteria::TIME_ALL))
            ->setFederation($federation ?? Criteria::AP_ALL)
            ->setTag($tagManager->transliterate(strtolower($name)));
        $this->setSort($criteria, $sortBy, $security);
        $this->handleLanguageCriteria($criteria);
        $criteria->content = Criteria::CONTENT_MICROBLOG;
        $criteria->perPage = $this->getPerPage($request, PostRepository::PER_PAGE);

        $user = $security->getUser();
        if ($user instanceof User) {
            $criteria->fetchCachedItems($sqlHelpers, $user);
        }

        $posts = $postRepository->findByCriteria($criteria);

        $dtos = [];
        foreach ($posts->getCurrentPageResults() as $value) {
            try {
                \assert($value instanceof Post);
                $this->handlePrivateContent($value);
                $dtos[] = $this->serializePost($factory->createDto($value), $this->tagLinkRepository->getTagsOfContent($value));
            } catch (AccessDeniedException $e) {
                continue;
            }
        }

        return new JsonResponse(
            $this->serializePaginated($dtos, $posts),
            headers: $headers
        );
    }

    #[OA\Response(
        response: 200,
        description: 'Returns a paginated list of post-comments which have the specific hashtag',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: EntryCommentResponseDto::class))
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
        name: 'name',
        in: 'path',
        description: 'The tag to search for',
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'sortBy',
        in: 'query',
        description: 'The sorting method to use during item fetch (if the user is logged-in the default from their settings will be used)',
        schema: new OA\Schema(
            type: 'string',
            default: EntryPageView::SORT_DEFAULT,
            enum: EntryPageView::SORT_OPTIONS
        )
    )]
    #[OA\Parameter(
        name: 'time',
        in: 'query',
        description: 'The maximum age of retrieved items',
        schema: new OA\Schema(
            type: 'string',
            default: Criteria::TIME_ALL,
            enum: Criteria::TIME_ROUTES_EN
        )
    )]
    #[OA\Parameter(
        name: 'federation',
        in: 'query',
        description: 'Whether to include federated content or not',
        schema: new OA\Schema(
            type: 'string',
            default: Criteria::AP_ALL,
            enum: Criteria::AP_OPTIONS
        )
    )]
    #[OA\Parameter(
        name: 'p',
        description: 'Page of items to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
    )]
    #[OA\Parameter(
        name: 'perPage',
        description: 'Number of items to retrieve per page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: EntryRepository::PER_PAGE, minimum: self::MIN_PER_PAGE, maximum: self::MAX_PER_PAGE)
    )]
    #[OA\Parameter(
        name: 'lang[]',
        description: 'Language(s) of comments to return',
        in: 'query',
        explode: true,
        allowReserved: true,
        schema: new OA\Schema(
            type: 'array',
            items: new OA\Items(type: 'string', default: null, minLength: 2, maxLength: 3)
        )
    )]
    #[OA\Parameter(
        name: 'usePreferredLangs',
        description: 'Filter by a user\'s preferred languages (Requires authentication and takes precedence over lang[])',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false),
    )]
    #[OA\Tag(name: 'tag')]
    public function postComments(
        string $name,
        #[MapQueryParameter]
        ?string $sortBy,
        #[MapQueryParameter]
        ?string $time,
        #[MapQueryParameter]
        ?string $federation,
        PostCommentRepository $commentRepository,
        PostCommentFactory $factory,
        TagExtractor $tagManager,
        SqlHelpers $sqlHelpers,
        Security $security,
        RateLimiterFactoryInterface $apiReadLimiter,
        RateLimiterFactoryInterface $anonymousApiReadLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);
        $request = $this->request->getCurrentRequest();

        $criteria = new PostCommentPageView($this->getPageNb($request), $security);
        $criteria->setTime($criteria->resolveTime($time ?? Criteria::TIME_ALL))
            ->setFederation($federation ?? Criteria::AP_ALL)
            ->setTag($tagManager->transliterate(strtolower($name)));
        $this->setSort($criteria, $sortBy, $security);
        $this->handleLanguageCriteria($criteria);
        $criteria->content = Criteria::CONTENT_THREADS;
        $criteria->perPage = $this->getPerPage($request, PostCommentRepository::PER_PAGE);

        $user = $security->getUser();
        if ($user instanceof User) {
            $criteria->fetchCachedItems($sqlHelpers, $user);
        }

        $comments = $commentRepository->findByCriteria($criteria);

        $dtos = [];
        foreach ($comments->getCurrentPageResults() as $value) {
            try {
                \assert($value instanceof PostComment);
                $this->handlePrivateContent($value);
                $dtos[] = $this->serializePostComment($factory->createDto($value), $this->tagLinkRepository->getTagsOfContent($value));
            } catch (AccessDeniedException $e) {
                continue;
            }
        }

        return new JsonResponse(
            $this->serializePaginated($dtos, $comments),
            headers: $headers
        );
    }

    private function setSort(Criteria $criteria, ?string $sortBy, Security $security): void
    {
        $user = $security->getUser();
        if (null !== $sortBy) {
            $criteria->showSortOption($sortBy);
        } elseif ($user instanceof User) {
            $criteria->showSortOption($user->frontDefaultSort);
        } else {
            $criteria->showSortOption(Criteria::SORT_HOT);
        }
    }
}
