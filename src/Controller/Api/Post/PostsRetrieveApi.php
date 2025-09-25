<?php

declare(strict_types=1);

namespace App\Controller\Api\Post;

use App\Controller\Traits\PrivateContentTrait;
use App\DTO\PostResponseDto;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Event\Post\PostHasBeenSeenEvent;
use App\Factory\PostFactory;
use App\PageView\PostPageView;
use App\Repository\ContentRepository;
use App\Repository\Criteria;
use App\Repository\PostRepository;
use App\Schema\PaginationSchema;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\SecurityBundle\Security as SymfonySecurity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PostsRetrieveApi extends PostsBaseApi
{
    use PrivateContentTrait;

    #[OA\Response(
        response: 200,
        description: 'The retrieved post',
        content: new Model(type: PostResponseDto::class),
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
        description: 'The post to retrieve',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Tag(name: 'post')]
    public function __invoke(
        #[MapEntity(id: 'post_id')]
        Post $post,
        PostFactory $factory,
        EventDispatcherInterface $dispatcher,
        RateLimiterFactory $apiReadLimiter,
        RateLimiterFactory $anonymousApiReadLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $this->handlePrivateContent($post);

        $dispatcher->dispatch(new PostHasBeenSeenEvent($post));

        $dto = $factory->createDto($post);

        return new JsonResponse(
            $this->serializePost($dto, $this->tagLinkRepository->getTagsOfContent($post)),
            headers: $headers
        );
    }

    #[OA\Response(
        response: 200,
        description: 'A paginated list of posts',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: PostResponseDto::class))
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
        description: 'Page of posts to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
    )]
    #[OA\Parameter(
        name: 'perPage',
        description: 'Number of posts to retrieve per page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: PostRepository::PER_PAGE, minimum: self::MIN_PER_PAGE, maximum: self::MAX_PER_PAGE)
    )]
    #[OA\Parameter(
        name: 'sort',
        description: 'Sort method to use when retrieving posts',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: Criteria::SORT_HOT, enum: Criteria::SORT_OPTIONS)
    )]
    #[OA\Parameter(
        name: 'time',
        description: 'Max age of retrieved posts',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: Criteria::TIME_ALL, enum: Criteria::TIME_ROUTES_EN)
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
    #[OA\Tag(name: 'post')]
    public function collection(
        PostRepository $repository,
        PostFactory $factory,
        RequestStack $request,
        RateLimiterFactory $apiReadLimiter,
        RateLimiterFactory $anonymousApiReadLimiter,
        SymfonySecurity $security,
        #[MapQueryParameter] ?string $federation,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $criteria = new PostPageView((int) $request->getCurrentRequest()->get('p', 1), $security);
        $criteria->sortOption = $request->getCurrentRequest()->get('sort', Criteria::SORT_HOT);
        $criteria->time = $criteria->resolveTime(
            $request->getCurrentRequest()->get('time', Criteria::TIME_ALL)
        );
        $criteria->perPage = self::constrainPerPage($request->getCurrentRequest()->get('perPage', PostRepository::PER_PAGE));
        $criteria->setFederation($federation ?? Criteria::AP_ALL);

        $this->handleLanguageCriteria($criteria);

        $posts = $repository->findByCriteria($criteria);

        $dtos = [];
        foreach ($posts->getCurrentPageResults() as $value) {
            try {
                \assert($value instanceof Post);
                $this->handlePrivateContent($value);
                array_push($dtos, $this->serializePost($factory->createDto($value), $this->tagLinkRepository->getTagsOfContent($value)));
            } catch (\Exception $e) {
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
        description: 'A paginated list of posts from user\'s subscribed magazines',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: PostResponseDto::class))
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
        description: 'Page of posts to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
    )]
    #[OA\Parameter(
        name: 'perPage',
        description: 'Number of posts to retrieve per page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: PostRepository::PER_PAGE, minimum: self::MIN_PER_PAGE, maximum: self::MAX_PER_PAGE)
    )]
    #[OA\Parameter(
        name: 'sort',
        description: 'Sort method to use when retrieving posts',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: Criteria::SORT_HOT, enum: Criteria::SORT_OPTIONS)
    )]
    #[OA\Parameter(
        name: 'time',
        description: 'Max age of retrieved posts',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: Criteria::TIME_ALL, enum: Criteria::TIME_ROUTES_EN)
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
    #[OA\Tag(name: 'post')]
    #[Security(name: 'oauth2', scopes: ['read'])]
    #[IsGranted('ROLE_OAUTH2_READ')]
    public function subscribed(
        ContentRepository $repository,
        PostFactory $factory,
        RateLimiterFactory $apiReadLimiter,
        RateLimiterFactory $anonymousApiReadLimiter,
        SymfonySecurity $security,
        #[MapQueryParameter] ?int $p,
        #[MapQueryParameter] ?int $perPage,
        #[MapQueryParameter] ?string $sort,
        #[MapQueryParameter] ?string $time,
        #[MapQueryParameter] ?string $federation,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $criteria = new PostPageView($p ?? 1, $security);
        $criteria->sortOption = $sort ?? Criteria::SORT_HOT;
        $criteria->time = $criteria->resolveTime($time ?? Criteria::TIME_ALL);
        $criteria->perPage = self::constrainPerPage($perPage ?? ContentRepository::PER_PAGE);
        $criteria->setFederation($federation ?? Criteria::AP_ALL);

        $criteria->subscribed = true;
        $criteria->setContent(Criteria::CONTENT_MICROBLOG);

        $this->handleLanguageCriteria($criteria);

        $posts = $repository->findByCriteria($criteria);

        $dtos = [];
        foreach ($posts->getCurrentPageResults() as $value) {
            try {
                \assert($value instanceof Post);
                $this->handlePrivateContent($value);
                array_push($dtos, $this->serializePost($factory->createDto($value), $this->tagLinkRepository->getTagsOfContent($value)));
            } catch (\Exception $e) {
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
        description: 'A paginated list of posts from user\'s moderated magazines',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: PostResponseDto::class))
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
        response: 403,
        description: 'The client does not have permission to perform moderation actions on posts',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\ForbiddenErrorSchema::class))
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
        description: 'Page of posts to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
    )]
    #[OA\Parameter(
        name: 'perPage',
        description: 'Number of posts to retrieve per page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: PostRepository::PER_PAGE, minimum: self::MIN_PER_PAGE, maximum: self::MAX_PER_PAGE)
    )]
    #[OA\Parameter(
        name: 'sort',
        description: 'Sort method to use when retrieving posts',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: Criteria::SORT_NEW, enum: Criteria::SORT_OPTIONS)
    )]
    #[OA\Parameter(
        name: 'time',
        description: 'Max age of retrieved posts',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: Criteria::TIME_ALL, enum: Criteria::TIME_ROUTES_EN)
    )]
    #[OA\Parameter(
        name: 'federation',
        description: 'What type of federated entries to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: Criteria::AP_ALL, enum: Criteria::AP_OPTIONS)
    )]
    #[OA\Tag(name: 'post')]
    #[Security(name: 'oauth2', scopes: ['moderate:post'])]
    #[IsGranted('ROLE_OAUTH2_MODERATE:POST')]
    public function moderated(
        ContentRepository $repository,
        PostFactory $factory,
        RateLimiterFactory $apiReadLimiter,
        RateLimiterFactory $anonymousApiReadLimiter,
        SymfonySecurity $security,
        #[MapQueryParameter] ?int $p,
        #[MapQueryParameter] ?int $perPage,
        #[MapQueryParameter] ?string $sort,
        #[MapQueryParameter] ?string $time,
        #[MapQueryParameter] ?string $federation,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $criteria = new PostPageView($p ?? 1, $security);
        $criteria->sortOption = $sort ?? Criteria::SORT_HOT;
        $criteria->time = $criteria->resolveTime($time ?? Criteria::TIME_ALL);
        $criteria->perPage = self::constrainPerPage($perPage ?? ContentRepository::PER_PAGE);
        $criteria->setFederation($federation ?? Criteria::AP_ALL);

        $criteria->moderated = true;
        $criteria->setContent(Criteria::CONTENT_MICROBLOG);

        $posts = $repository->findByCriteria($criteria);

        $dtos = [];
        foreach ($posts->getCurrentPageResults() as $value) {
            try {
                \assert($value instanceof Post);
                $this->handlePrivateContent($value);
                array_push($dtos, $this->serializePost($factory->createDto($value), $this->tagLinkRepository->getTagsOfContent($value)));
            } catch (\Exception $e) {
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
        description: 'A paginated list of user\'s favourited posts',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: PostResponseDto::class))
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
        description: 'Page of posts to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
    )]
    #[OA\Parameter(
        name: 'perPage',
        description: 'Number of posts to retrieve per page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: PostRepository::PER_PAGE, minimum: self::MIN_PER_PAGE, maximum: self::MAX_PER_PAGE)
    )]
    #[OA\Parameter(
        name: 'sort',
        description: 'Sort method to use when retrieving posts',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: Criteria::SORT_HOT, enum: Criteria::SORT_OPTIONS)
    )]
    #[OA\Parameter(
        name: 'time',
        description: 'Max age of retrieved posts',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: Criteria::TIME_ALL, enum: Criteria::TIME_ROUTES_EN)
    )]
    #[OA\Parameter(
        name: 'federation',
        description: 'What type of federated entries to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: Criteria::AP_ALL, enum: Criteria::AP_OPTIONS)
    )]
    #[OA\Tag(name: 'post')]
    #[Security(name: 'oauth2', scopes: ['post:vote'])]
    #[IsGranted('ROLE_OAUTH2_POST:VOTE')]
    public function favourited(
        ContentRepository $repository,
        PostFactory $factory,
        RateLimiterFactory $apiReadLimiter,
        RateLimiterFactory $anonymousApiReadLimiter,
        SymfonySecurity $security,
        #[MapQueryParameter] ?int $p,
        #[MapQueryParameter] ?int $perPage,
        #[MapQueryParameter] ?string $sort,
        #[MapQueryParameter] ?string $time,
        #[MapQueryParameter] ?string $federation,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $criteria = new PostPageView($p ?? 1, $security);
        $criteria->sortOption = $sort ?? Criteria::SORT_HOT;
        $criteria->time = $criteria->resolveTime($time ?? Criteria::TIME_ALL);
        $criteria->perPage = self::constrainPerPage($perPage ?? ContentRepository::PER_PAGE);
        $criteria->setFederation($federation ?? Criteria::AP_ALL);

        $criteria->favourite = true;
        $criteria->setContent(Criteria::CONTENT_MICROBLOG);

        $this->logger->debug(var_export($criteria, true));

        $posts = $repository->findByCriteria($criteria);

        $dtos = [];
        foreach ($posts->getCurrentPageResults() as $value) {
            try {
                \assert($value instanceof Post);
                $this->handlePrivateContent($value);
                array_push($dtos, $this->serializePost($factory->createDto($value), $this->tagLinkRepository->getTagsOfContent($value)));
            } catch (\Exception $e) {
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
        description: 'A paginated list of posts from the magazine',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: PostResponseDto::class))
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
        response: 404,
        description: 'Magazine not found',
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
        name: 'magazine_id',
        description: 'Magazine to retrieve posts from',
        in: 'path',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'p',
        description: 'Page of posts to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
    )]
    #[OA\Parameter(
        name: 'perPage',
        description: 'Number of posts to retrieve per page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: PostRepository::PER_PAGE, minimum: self::MIN_PER_PAGE, maximum: self::MAX_PER_PAGE)
    )]
    #[OA\Parameter(
        name: 'sort',
        description: 'Sort method to use when retrieving posts',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: Criteria::SORT_HOT, enum: Criteria::SORT_OPTIONS)
    )]
    #[OA\Parameter(
        name: 'time',
        description: 'Max age of retrieved posts',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: Criteria::TIME_ALL, enum: Criteria::TIME_ROUTES_EN)
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
    #[OA\Tag(name: 'magazine')]
    public function byMagazine(
        #[MapEntity(id: 'magazine_id')]
        Magazine $magazine,
        ContentRepository $repository,
        PostFactory $factory,
        RateLimiterFactory $apiReadLimiter,
        RateLimiterFactory $anonymousApiReadLimiter,
        SymfonySecurity $security,
        #[MapQueryParameter] ?int $p,
        #[MapQueryParameter] ?int $perPage,
        #[MapQueryParameter] ?string $sort,
        #[MapQueryParameter] ?string $time,
        #[MapQueryParameter] ?string $federation,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $criteria = new PostPageView($p ?? 1, $security);
        $criteria->sortOption = $sort ?? Criteria::SORT_HOT;
        $criteria->time = $criteria->resolveTime($time ?? Criteria::TIME_ALL);
        $criteria->perPage = self::constrainPerPage($perPage ?? ContentRepository::PER_PAGE);
        $criteria->setFederation($federation ?? Criteria::AP_ALL);
        $criteria->stickiesFirst = true;

        $this->handleLanguageCriteria($criteria);

        $criteria->magazine = $magazine;
        $criteria->setContent(Criteria::CONTENT_MICROBLOG);

        $posts = $repository->findByCriteria($criteria);

        $dtos = [];
        foreach ($posts->getCurrentPageResults() as $value) {
            try {
                \assert($value instanceof Post);
                $this->handlePrivateContent($value);
                array_push($dtos, $this->serializePost($factory->createDto($value), $this->tagLinkRepository->getTagsOfContent($value)));
            } catch (\Exception $e) {
                continue;
            }
        }

        return new JsonResponse(
            $this->serializePaginated($dtos, $posts),
            headers: $headers
        );
    }
}
