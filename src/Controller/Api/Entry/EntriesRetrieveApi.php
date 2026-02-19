<?php

declare(strict_types=1);

namespace App\Controller\Api\Entry;

use App\Controller\Traits\PrivateContentTrait;
use App\DTO\EntryResponseDto;
use App\Entity\Entry;
use App\Entity\User;
use App\Event\Entry\EntryHasBeenSeenEvent;
use App\Factory\EntryFactory;
use App\PageView\EntryPageView;
use App\Repository\ContentRepository;
use App\Repository\Criteria;
use App\Repository\EntryRepository;
use App\Schema\PaginationSchema;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\SecurityBundle\Security as SymfonySecurity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class EntriesRetrieveApi extends EntriesBaseApi
{
    use PrivateContentTrait;

    #[OA\Response(
        response: 200,
        description: 'Returns the Entry',
        content: new Model(type: EntryResponseDto::class),
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
        description: 'Entry not found',
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
        name: 'entry_id',
        in: 'path',
        description: 'The entry to retrieve',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Tag(name: 'entry')]
    public function __invoke(
        #[MapEntity(id: 'entry_id')]
        Entry $entry,
        EntryFactory $factory,
        EventDispatcherInterface $dispatcher,
        RateLimiterFactoryInterface $apiReadLimiter,
        RateLimiterFactoryInterface $anonymousApiReadLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $this->handlePrivateContent($entry);

        $dispatcher->dispatch(new EntryHasBeenSeenEvent($entry));

        $dto = $factory->createDto($entry);

        return new JsonResponse(
            $this->serializeEntry($dto, $this->tagLinkRepository->getTagsOfContent($entry), $this->entryRepository->findCross($entry)),
            headers: $headers
        );
    }

    #[OA\Response(
        response: 200,
        description: 'Returns a list of Entries',
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
        name: 'sort',
        in: 'query',
        description: 'The sorting method to use during entry fetch',
        schema: new OA\Schema(
            default: Criteria::SORT_DEFAULT,
            enum: Criteria::SORT_OPTIONS
        )
    )]
    #[OA\Parameter(
        name: 'time',
        in: 'query',
        description: 'The maximum age of retrieved entries',
        schema: new OA\Schema(
            default: Criteria::TIME_ALL,
            enum: Criteria::TIME_ROUTES_EN
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
            items: new OA\Items(type: 'string')
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
    #[OA\Tag(name: 'entry')]
    public function collection(
        ContentRepository $repository,
        EntryFactory $factory,
        RateLimiterFactoryInterface $apiReadLimiter,
        RateLimiterFactoryInterface $anonymousApiReadLimiter,
        SymfonySecurity $security,
        #[MapQueryParameter] ?int $p,
        #[MapQueryParameter] ?string $sort,
        #[MapQueryParameter] ?string $time,
        #[MapQueryParameter] ?string $federation,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $criteria = new EntryPageView($p ?? 1, $security);
        $criteria->sortOption = $sort ?? Criteria::SORT_HOT;
        $criteria->time = $criteria->resolveTime($time ?? Criteria::TIME_ALL);
        $criteria->setFederation($federation ?? Criteria::AP_ALL);
        $this->handleLanguageCriteria($criteria);
        $criteria->content = Criteria::CONTENT_THREADS;

        $user = $security->getUser();
        if ($user instanceof User) {
            $criteria->fetchCachedItems($repository, $user);
        }

        $entries = $repository->findByCriteria($criteria);

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
        description: 'Returns a list of entries from subscribed magazines',
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
        name: 'sort',
        in: 'query',
        description: 'The sorting method to use during entry fetch',
        schema: new OA\Schema(
            default: Criteria::SORT_DEFAULT,
            enum: Criteria::SORT_OPTIONS
        )
    )]
    #[OA\Parameter(
        name: 'time',
        in: 'query',
        description: 'The maximum age of retrieved entries',
        schema: new OA\Schema(
            default: Criteria::TIME_ALL,
            enum: Criteria::TIME_ROUTES_EN
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
        schema: new OA\Schema(type: 'integer', default: EntryRepository::PER_PAGE, minimum: self::MIN_PER_PAGE, maximum: self::MAX_PER_PAGE)
    )]
    #[OA\Parameter(
        name: 'federation',
        description: 'What type of federated entries to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: Criteria::AP_ALL, enum: Criteria::AP_OPTIONS)
    )]
    #[OA\Tag(name: 'entry')]
    #[Security(name: 'oauth2', scopes: ['read'])]
    #[IsGranted('ROLE_OAUTH2_READ')]
    public function subscribed(
        ContentRepository $repository,
        EntryFactory $factory,
        RateLimiterFactoryInterface $apiReadLimiter,
        SymfonySecurity $security,
        #[MapQueryParameter] ?int $p,
        #[MapQueryParameter] ?string $sort,
        #[MapQueryParameter] ?string $time,
        #[MapQueryParameter] ?string $federation,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter);

        $criteria = new EntryPageView($p ?? 1, $security);
        $criteria->sortOption = $sort ?? Criteria::SORT_HOT;
        $criteria->time = $criteria->resolveTime($time ?? Criteria::TIME_ALL);
        $criteria->setFederation($federation ?? Criteria::AP_ALL);

        $criteria->subscribed = true;
        $criteria->content = Criteria::CONTENT_THREADS;

        $user = $security->getUser();
        if ($user instanceof User) {
            $criteria->fetchCachedItems($repository, $user);
        }

        $entries = $repository->findByCriteria($criteria);

        $dtos = [];
        foreach ($entries->getCurrentPageResults() as $value) {
            try {
                \assert($value instanceof Entry);
                $this->handlePrivateContent($value);
                $dtos[] = $this->serializeEntry($factory->createDto($value), $this->tagLinkRepository->getTagsOfContent($value));
            } catch (\Exception $e) {
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
        description: 'Returns a list of entries from moderated magazines',
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
        name: 'sort',
        in: 'query',
        description: 'The sorting method to use during entry fetch',
        schema: new OA\Schema(
            default: Criteria::SORT_NEW,
            enum: Criteria::SORT_OPTIONS
        )
    )]
    #[OA\Parameter(
        name: 'time',
        in: 'query',
        description: 'The maximum age of retrieved entries',
        schema: new OA\Schema(
            default: Criteria::TIME_ALL,
            enum: Criteria::TIME_ROUTES_EN
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
        schema: new OA\Schema(type: 'integer', default: EntryRepository::PER_PAGE, minimum: self::MIN_PER_PAGE, maximum: self::MAX_PER_PAGE)
    )]
    #[OA\Parameter(
        name: 'federation',
        description: 'What type of federated entries to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: Criteria::AP_ALL, enum: Criteria::AP_OPTIONS)
    )]
    #[OA\Tag(name: 'entry')]
    #[Security(name: 'oauth2', scopes: ['moderate:entry'])]
    #[IsGranted('ROLE_OAUTH2_MODERATE:ENTRY')]
    public function moderated(
        ContentRepository $repository,
        EntryFactory $factory,
        RateLimiterFactoryInterface $apiReadLimiter,
        SymfonySecurity $security,
        #[MapQueryParameter] ?int $p,
        #[MapQueryParameter] ?string $sort,
        #[MapQueryParameter] ?string $time,
        #[MapQueryParameter] ?string $federation,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter);

        $criteria = new EntryPageView($p ?? 1, $security);
        $criteria->sortOption = $sort ?? Criteria::SORT_HOT;
        $criteria->time = $criteria->resolveTime($time ?? Criteria::TIME_ALL);
        $criteria->setFederation($federation ?? Criteria::AP_ALL);

        $criteria->moderated = true;
        $criteria->content = Criteria::CONTENT_THREADS;

        $user = $security->getUser();
        if ($user instanceof User) {
            $criteria->fetchCachedItems($repository, $user);
        }

        $entries = $repository->findByCriteria($criteria);

        $dtos = [];
        foreach ($entries->getCurrentPageResults() as $value) {
            try {
                \assert($value instanceof Entry);
                $this->handlePrivateContent($value);
                $dtos[] = $this->serializeEntry($factory->createDto($value), $this->tagLinkRepository->getTagsOfContent($value));
            } catch (\Exception $e) {
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
        description: 'Returns a list of favourited entries',
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
        name: 'sort',
        in: 'query',
        description: 'The sorting method to use during entry fetch',
        schema: new OA\Schema(
            default: Criteria::SORT_TOP,
            enum: Criteria::SORT_OPTIONS
        )
    )]
    #[OA\Parameter(
        name: 'time',
        in: 'query',
        description: 'The maximum age of retrieved entries',
        schema: new OA\Schema(
            default: Criteria::TIME_ALL,
            enum: Criteria::TIME_ROUTES_EN
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
        schema: new OA\Schema(type: 'integer', default: EntryRepository::PER_PAGE, minimum: self::MIN_PER_PAGE, maximum: self::MAX_PER_PAGE)
    )]
    #[OA\Parameter(
        name: 'federation',
        description: 'What type of federated entries to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: Criteria::AP_ALL, enum: Criteria::AP_OPTIONS)
    )]
    #[OA\Tag(name: 'entry')]
    #[Security(name: 'oauth2', scopes: ['entry:vote'])]
    #[IsGranted('ROLE_OAUTH2_ENTRY:VOTE')]
    public function favourited(
        ContentRepository $repository,
        EntryFactory $factory,
        RateLimiterFactoryInterface $apiReadLimiter,
        SymfonySecurity $security,
        #[MapQueryParameter] ?int $p,
        #[MapQueryParameter] ?string $sort,
        #[MapQueryParameter] ?string $time,
        #[MapQueryParameter] ?string $federation,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter);

        $criteria = new EntryPageView($p ?? 1, $security);
        $criteria->sortOption = $sort ?? Criteria::SORT_HOT;
        $criteria->time = $criteria->resolveTime($time ?? Criteria::TIME_ALL);
        $criteria->setFederation($federation ?? Criteria::AP_ALL);

        $criteria->favourite = true;

        $user = $security->getUser();
        if ($user instanceof User) {
            $criteria->fetchCachedItems($repository, $user);
        }

        $entries = $repository->findByCriteria($criteria);
        $criteria->content = Criteria::CONTENT_THREADS;

        $dtos = [];
        foreach ($entries->getCurrentPageResults() as $value) {
            try {
                \assert($value instanceof Entry);
                $this->handlePrivateContent($value);
                $dtos[] = $this->serializeEntry($factory->createDto($value), $this->tagLinkRepository->getTagsOfContent($value));
            } catch (\Exception $e) {
                continue;
            }
        }

        return new JsonResponse(
            $this->serializePaginated($dtos, $entries),
            headers: $headers
        );
    }
}
