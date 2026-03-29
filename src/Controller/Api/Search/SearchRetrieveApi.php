<?php

declare(strict_types=1);

namespace App\Controller\Api\Search;

use App\Controller\Api\BaseApi;
use App\Controller\Traits\PrivateContentTrait;
use App\DTO\SearchResponseDto;
use App\Entity\Contracts\ContentInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Factory\MagazineFactory;
use App\Factory\UserFactory;
use App\Repository\SearchRepository;
use App\Schema\ContentSchema;
use App\Schema\PaginationSchema;
use App\Schema\SearchActorSchema;
use App\Service\SearchManager;
use App\Service\SettingsManager;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

class SearchRetrieveApi extends BaseApi
{
    use PrivateContentTrait;

    #[OA\Response(
        response: 200,
        description: 'Returns a paginated list of content, along with any ActivityPub actors that matched the query by username, or ActivityPub objects that matched the query by URL. Actors and objects are not paginated',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(
                        ref: new Model(type: ContentSchema::class)
                    )
                ),
                new OA\Property(
                    property: 'pagination',
                    ref: new Model(type: PaginationSchema::class)
                ),
                new OA\Property(
                    property: 'apActors',
                    type: 'array',
                    items: new OA\Items(
                        ref: new Model(type: SearchActorSchema::class)
                    )
                ),
                new OA\Property(
                    property: 'apObjects',
                    type: 'array',
                    items: new OA\Items(
                        ref: new Model(type: ContentSchema::class)
                    )
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
    #[OA\Parameter(
        name: 'authorId',
        description: 'User id of the author',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'magazineId',
        description: 'Id of the magazine',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'type',
        description: 'The type of content',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['', 'entry', 'post'])
    )]
    #[OA\Tag(name: 'search')]
    #[OA\Get(deprecated: true)]
    public function searchV1(
        SearchManager $manager,
        UserFactory $userFactory,
        MagazineFactory $magazineFactory,
        SettingsManager $settingsManager,
        RateLimiterFactoryInterface $apiReadLimiter,
        RateLimiterFactoryInterface $anonymousApiReadLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $request = $this->request->getCurrentRequest();
        $q = $request->get('q');
        if (null === $q) {
            throw new BadRequestHttpException();
        }

        $page = $this->getPageNb($request);
        $perPage = self::constrainPerPage($request->get('perPage', SearchRepository::PER_PAGE));
        $authorIdRaw = $request->get('authorId');
        $authorId = null === $authorIdRaw ? null : \intval($authorIdRaw);
        $magazineIdRaw = $request->get('magazineId');
        $magazineId = null === $magazineIdRaw ? null : \intval($magazineIdRaw);
        $type = $request->get('type');
        if ('entry' !== $type && 'post' !== $type && null !== $type) {
            throw new BadRequestHttpException();
        }

        $items = $manager->findPaginated($this->getUser(), $q, $page, $perPage, authorId: $authorId, magazineId: $magazineId, specificType: $type);
        $dtos = [];
        foreach ($items->getCurrentPageResults() as $value) {
            \assert($value instanceof ContentInterface);
            array_push($dtos, $this->serializeContentInterface($value));
        }

        $response = $this->serializePaginated($dtos, $items);

        $response['apActors'] = [];
        $response['apObjects'] = [];
        $actors = [];
        $objects = [];
        if (!$settingsManager->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN') || $this->getUser()) {
            $actors = $manager->findActivityPubActorsByUsername($q);
            $objects = $manager->findActivityPubObjectsByURL($q);
        }

        foreach ($actors as $actor) {
            switch ($actor['type']) {
                case 'user':
                    $response['apActors'][] = [
                        'type' => 'user',
                        'object' => $this->serializeUser($userFactory->createDto($actor['object'])),
                    ];
                    break;
                case 'magazine':
                    $response['apActors'][] = [
                        'type' => 'magazine',
                        'object' => $this->serializeMagazine($magazineFactory->createDto($actor['object'])),
                    ];
                    break;
            }
        }

        foreach ($objects as $object) {
            \assert($object instanceof ContentInterface);
            $response['apObjects'][] = $this->serializeContentInterface($object);
        }

        return new JsonResponse(
            $response,
            headers: $headers
        );
    }

    #[OA\Response(
        response: 200,
        description: 'Returns a paginated list of content, along with any ActivityPub actors that matched the query by username, or ActivityPub objects that matched the query by URL. AP-Objects are not paginated.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: SearchResponseDto::class))
                ),
                new OA\Property(
                    property: 'pagination',
                    ref: new Model(type: PaginationSchema::class)
                ),
                new OA\Property(
                    property: 'apResults',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: SearchResponseDto::class))
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
    #[OA\Parameter(
        name: 'authorId',
        description: 'User id of the author',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'magazineId',
        description: 'Id of the magazine',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'type',
        description: 'The type of content',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['', 'entry', 'post'])
    )]
    #[OA\Tag(name: 'search')]
    public function searchV2(
        SearchManager $manager,
        RateLimiterFactoryInterface $apiReadLimiter,
        RateLimiterFactoryInterface $anonymousApiReadLimiter,
        #[MapQueryParameter]
        string $q,
        #[MapQueryParameter]
        int $perPage = SearchRepository::PER_PAGE,
        #[MapQueryParameter('authorId')]
        ?int $authorId = null,
        #[MapQueryParameter('magazineId')]
        ?int $magazineId = null,
        #[MapQueryParameter]
        ?string $type = null,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $request = $this->request->getCurrentRequest();
        $page = $this->getPageNb($request);

        if ('entry' !== $type && 'post' !== $type && null !== $type) {
            throw new BadRequestHttpException();
        }

        /** @var ?SearchResponseDto[] $searchResults */
        $searchResults = [];
        $items = $manager->findPaginated($this->getUser(), $q, $page, $perPage, authorId: $authorId, magazineId: $magazineId, specificType: $type);
        foreach ($items->getCurrentPageResults() as $item) {
            $searchResults[] = $this->serializeItem($item);
        }

        /** @var ?SearchResponseDto $apResults */
        $apResults = [];
        if ($this->federatedSearchAllowed()) {
            $objects = $manager->findActivityPubActorsOrObjects($q);

            foreach ($objects['errors'] as $error) {
                /** @var \Throwable $error */
                $this->logger->warning(
                    'Exception while resolving AP handle / url {q}: {type}: {msg}',
                    [
                        'q' => $q,
                        'type' => \get_class($error),
                        'msg' => $error->getMessage(),
                    ]
                );
            }

            foreach ($objects['results'] as $object) {
                $apResults[] = $this->serializeItem($object['object']);
            }
        }

        $response = $this->serializePaginated($searchResults, $items);
        $response['apResults'] = $apResults;

        return new JsonResponse(
            $response,
            headers: $headers
        );
    }

    private function federatedSearchAllowed(): bool
    {
        return !$this->settingsManager->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN')
            || $this->getUser();
    }

    private function serializeItem(object $item): ?SearchResponseDto
    {
        if ($item instanceof Entry) {
            $this->handlePrivateContent($item);

            return new SearchResponseDto(entry: $this->serializeEntry($this->entryFactory->createDto($item), $this->tagLinkRepository->getTagsOfContent($item)));
        } elseif ($item instanceof Post) {
            $this->handlePrivateContent($item);

            return new SearchResponseDto(post: $this->serializePost($this->postFactory->createDto($item), $this->tagLinkRepository->getTagsOfContent($item)));
        } elseif ($item instanceof EntryComment) {
            $this->handlePrivateContent($item);

            return new SearchResponseDto(entryComment: $this->serializeEntryComment($this->entryCommentFactory->createDto($item), $this->tagLinkRepository->getTagsOfContent($item)));
        } elseif ($item instanceof PostComment) {
            $this->handlePrivateContent($item);

            return new SearchResponseDto(postComment: $this->serializePostComment($this->postCommentFactory->createDto($item), $this->tagLinkRepository->getTagsOfContent($item)));
        } elseif ($item instanceof Magazine) {
            return new SearchResponseDto(magazine: $this->serializeMagazine($this->magazineFactory->createDto($item)));
        } elseif ($item instanceof User) {
            return new SearchResponseDto(user: $this->serializeUser($this->userFactory->createDto($item)));
        } else {
            $this->logger->error('Unexpected result type: '.\get_class($item));

            return null;
        }
    }
}
