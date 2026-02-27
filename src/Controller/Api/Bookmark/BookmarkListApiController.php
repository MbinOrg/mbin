<?php

declare(strict_types=1);

namespace App\Controller\Api\Bookmark;

use App\Controller\Api\BaseApi;
use App\DTO\BookmarkListDto;
use App\Entity\BookmarkList;
use App\Entity\Contracts\ContentInterface;
use App\Entity\Entry;
use App\PageView\EntryPageView;
use App\Repository\Criteria;
use App\Repository\EntryRepository;
use App\Schema\ContentSchema;
use App\Schema\Errors\BadRequestErrorSchema;
use App\Schema\Errors\NotFoundErrorSchema;
use App\Schema\Errors\TooManyRequestsErrorSchema;
use App\Schema\Errors\UnauthorizedErrorSchema;
use App\Schema\PaginationSchema;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\SecurityBundle\Security as SymfonySecurity;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class BookmarkListApiController extends BaseApi
{
    #[OA\Response(
        response: 200,
        description: 'Returns the content of a bookmark list',
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
                    items: new OA\Items(ref: new Model(type: ContentSchema::class))
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
        description: 'The requested list does not exist',
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
    #[OA\Parameter(
        name: 'list',
        description: 'The list from which to retrieve the bookmarks. If not set the default list will be used',
        in: 'query',
        schema: new OA\Schema(
            type: 'string',
            default: null
        )
    )]
    #[OA\Parameter(
        name: 'sort',
        description: 'The sorting method to use during entry fetch',
        in: 'query',
        schema: new OA\Schema(
            default: Criteria::SORT_DEFAULT,
            enum: Criteria::SORT_OPTIONS
        )
    )]
    #[OA\Parameter(
        name: 'time',
        description: 'The maximum age of retrieved entries',
        in: 'query',
        schema: new OA\Schema(
            default: Criteria::TIME_ALL,
            enum: Criteria::TIME_ROUTES_EN
        )
    )]
    #[OA\Parameter(
        name: 'federation',
        description: 'Whether to include federated posts',
        in: 'query',
        schema: new OA\Schema(
            default: Criteria::AP_ALL,
            enum: [Criteria::AP_ALL, Criteria::AP_LOCAL]
        )
    )]
    #[OA\Parameter(
        name: 'type',
        description: 'The type of entries to fetch. If set only entries will be returned',
        in: 'query',
        schema: new OA\Schema(
            default: 'all',
            enum: [...Entry::ENTRY_TYPE_OPTIONS, 'all']
        )
    )]
    #[OA\Parameter(
        name: 'p',
        description: 'Page of entries to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
    )]
    #[OA\Parameter(
        name: 'perPage',
        description: 'Number of entries to retrieve per page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: EntryRepository::PER_PAGE, maximum: self::MAX_PER_PAGE, minimum: self::MIN_PER_PAGE)
    )]
    #[OA\Tag(name: 'bookmark_list')]
    #[Security(name: 'oauth2', scopes: ['bookmark_list:read'])]
    #[IsGranted('ROLE_OAUTH2_BOOKMARK_LIST:READ')]
    public function front(
        #[MapQueryParameter] ?string $list,
        #[MapQueryParameter] ?string $sort,
        #[MapQueryParameter] ?string $time,
        #[MapQueryParameter] ?string $federation,
        #[MapQueryParameter] ?string $type,
        #[MapQueryParameter] ?int $p,
        #[MapQueryParameter] ?int $perPage,
        RateLimiterFactoryInterface $apiReadLimiter,
        SymfonySecurity $security,
    ): JsonResponse {
        $user = $this->getUserOrThrow();
        $headers = $this->rateLimit($apiReadLimiter);
        $criteria = new EntryPageView($p ?? 1, $security);
        $criteria->setTime($criteria->resolveTime($time ?? Criteria::TIME_ALL));
        $criteria->setType($criteria->resolveType($type ?? 'all'));
        $criteria->showSortOption($criteria->resolveSort($sort ?? Criteria::SORT_NEW));
        $criteria->setFederation($federation ?? Criteria::AP_ALL);

        if (null !== $list) {
            $bookmarkList = $this->bookmarkListRepository->findOneBy(['name' => $list, 'user' => $user]);
            if (null === $bookmarkList) {
                return new JsonResponse(status: 404, headers: $headers);
            }
        } else {
            $bookmarkList = $this->bookmarkListRepository->findOneByUserDefault($user);
        }
        $pagerfanta = $this->bookmarkRepository->findPopulatedByList($bookmarkList, $criteria, $perPage);
        $objects = $pagerfanta->getCurrentPageResults();
        $items = array_map(fn (ContentInterface $item) => $this->serializeContentInterface($item), $objects);
        $result = $this->serializePaginated($items, $pagerfanta);

        return new JsonResponse($result, status: 200, headers: $headers);
    }

    #[OA\Response(
        response: 200,
        description: 'Returns all bookmark lists from the user',
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
                    items: new OA\Items(ref: new Model(type: BookmarkListDto::class))
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
    #[OA\Tag(name: 'bookmark_list')]
    #[Security(name: 'oauth2', scopes: ['bookmark_list:read'])]
    #[IsGranted('ROLE_OAUTH2_BOOKMARK_LIST:READ')]
    public function list(RateLimiterFactoryInterface $apiReadLimiter): JsonResponse
    {
        $user = $this->getUserOrThrow();
        $headers = $this->rateLimit($apiReadLimiter);
        $items = array_map(fn (BookmarkList $list) => BookmarkListDto::fromList($list), $this->bookmarkListRepository->findByUser($user));
        $response = [
            'items' => $items,
        ];

        return new JsonResponse($response, status: 200, headers: $headers);
    }

    #[OA\Response(
        response: 200,
        description: 'Sets the provided list as the default',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new Model(type: BookmarkListDto::class),
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'The requested list does not exist',
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
    #[OA\Parameter(
        name: 'list_name',
        description: 'The name of the list to be made the default',
        in: 'path',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Tag(name: 'bookmark_list')]
    #[Security(name: 'oauth2', scopes: ['bookmark_list:edit'])]
    #[IsGranted('ROLE_OAUTH2_BOOKMARK_LIST:EDIT')]
    public function makeDefault(string $list_name, RateLimiterFactoryInterface $apiUpdateLimiter): JsonResponse
    {
        $user = $this->getUserOrThrow();
        $headers = $this->rateLimit($apiUpdateLimiter);
        $list = $this->bookmarkListRepository->findOneByUserAndName($user, $list_name);
        if (null === $list) {
            throw new NotFoundHttpException(headers: $headers);
        }
        $this->bookmarkListRepository->makeListDefault($user, $list);

        return new JsonResponse(BookmarkListDto::fromList($list), status: 200, headers: $headers);
    }

    #[OA\Response(
        response: 200,
        description: 'Edits the supplied list',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new Model(type: BookmarkListDto::class),
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'The requested list does not exist',
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
    #[OA\Parameter(
        name: 'list_name',
        description: 'The name of the list to be edited',
        in: 'path',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\RequestBody(content: new Model(type: BookmarkListDto::class))]
    #[OA\Tag(name: 'bookmark_list')]
    #[Security(name: 'oauth2', scopes: ['bookmark_list:edit'])]
    #[IsGranted('ROLE_OAUTH2_BOOKMARK_LIST:EDIT')]
    public function editList(string $list_name, #[MapRequestPayload] BookmarkListDto $dto, RateLimiterFactoryInterface $apiUpdateLimiter): JsonResponse
    {
        $user = $this->getUserOrThrow();
        $headers = $this->rateLimit($apiUpdateLimiter);
        $list = $this->bookmarkListRepository->findOneByUserAndName($user, $list_name);
        if (null === $list) {
            throw new NotFoundHttpException(headers: $headers);
        }
        $this->bookmarkListRepository->editList($user, $list, $dto);

        return new JsonResponse(BookmarkListDto::fromList($list), status: 200, headers: $headers);
    }

    #[OA\Response(
        response: 200,
        description: 'Creates a list with the supplied name',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new Model(type: BookmarkListDto::class),
    )]
    #[OA\Response(
        response: 400,
        description: 'The requested list already exists',
        content: new OA\JsonContent(ref: new Model(type: BadRequestErrorSchema::class))
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
        name: 'list_name',
        description: 'The name of the list to be created',
        in: 'path',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Tag(name: 'bookmark_list')]
    #[Security(name: 'oauth2', scopes: ['bookmark_list:edit'])]
    #[IsGranted('ROLE_OAUTH2_BOOKMARK_LIST:EDIT')]
    public function createList(string $list_name, RateLimiterFactoryInterface $apiUpdateLimiter): JsonResponse
    {
        $user = $this->getUserOrThrow();
        $headers = $this->rateLimit($apiUpdateLimiter);
        $list = $this->bookmarkListRepository->findOneByUserAndName($user, $list_name);
        if (null !== $list) {
            throw new BadRequestException();
        }
        $list = $this->bookmarkManager->createList($user, $list_name);

        return new JsonResponse(BookmarkListDto::fromList($list), status: 200, headers: $headers);
    }

    #[OA\Response(
        response: 200,
        description: 'Deletes the provided list',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: null
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'The requested list does not exist',
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
    #[OA\Parameter(
        name: 'list_name',
        description: 'The name of the list to be deleted',
        in: 'path',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Tag(name: 'bookmark_list')]
    #[Security(name: 'oauth2', scopes: ['bookmark_list:delete'])]
    #[IsGranted('ROLE_OAUTH2_BOOKMARK_LIST:DELETE')]
    public function deleteList(string $list_name, RateLimiterFactoryInterface $apiDeleteLimiter): JsonResponse
    {
        $user = $this->getUserOrThrow();
        $headers = $this->rateLimit($apiDeleteLimiter);
        $list = $this->bookmarkListRepository->findOneByUserAndName($user, $list_name);
        if (null === $list) {
            throw new NotFoundHttpException(headers: $headers);
        }
        $this->bookmarkListRepository->deleteList($list);

        return new JsonResponse(status: 200, headers: $headers);
    }
}
