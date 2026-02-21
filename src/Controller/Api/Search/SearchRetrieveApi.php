<?php

declare(strict_types=1);

namespace App\Controller\Api\Search;

use App\ActivityPub\ActorHandle;
use App\Controller\Api\BaseApi;
use App\Controller\Traits\PrivateContentTrait;
use App\Entity\Contracts\ContentInterface;
use App\Entity\Magazine;
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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

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
    public function __invoke(
        SearchManager $manager,
        UserFactory $userFactory,
        MagazineFactory $magazineFactory,
        SettingsManager $settingsManager,
        RateLimiterFactory $apiReadLimiter,
        RateLimiterFactory $anonymousApiReadLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $request = $this->request->getCurrentRequest();
        /** @var string $q */
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
            // TODO here we have two options
            //  A: skip non-content values
            //  B: add User and Magazine to the schema of the response; this might be a breaking API change
            if ($value instanceof ContentInterface) {
                $dtos[] = $this->serializeContentInterface($value);
            } elseif ($value instanceof User) {
                $dtos[] = $this->serializeUser($userFactory->createDto($value));
            } elseif ($value instanceof Magazine) {
                $dtos[] = $this->serializeMagazine($magazineFactory->createDto($value));
            } else {
                $this->logger->error('Unexpected result type: '.\get_class($value));
            }
        }

        $response = $this->serializePaginated($dtos, $items);

        $response['apActors'] = [];
        $response['apObjects'] = [];
        if ($this->federatedSearchAllowed()) {
            if ($handle = ActorHandle::parse($q)) {
                $actors = $manager->findActivityPubActorsByUsername($handle);
                $response['apActors'] = $this->transformActors($actors, $userFactory, $magazineFactory);
            }

            $objects = $manager->findActivityPubObjectsByURL($q);
            foreach ($objects['errors'] as $error) {
                /** @var \Exception $error */
                $this->logger->warning(
                    'Exception while resolving URL {url}: {type}: {msg}',
                    [
                        'url' => $q,
                        'type' => \get_class($error),
                        'msg' => $error->getMessage(),
                    ]
                );
            }

            $transformedObjects = $this->transformObjects($objects['results'], $userFactory, $magazineFactory);
            $response['apActors'] = [...$response['apActors'], ...$transformedObjects['actors']];
            $response['apObjects'] = $transformedObjects['content'];
        }

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

    private function transformActors(array $actors, UserFactory $userFactory, MagazineFactory $magazineFactory): array
    {
        $ret = [];
        foreach ($actors as $actor) {
            $ret[] = $this->transformActor($actor, $userFactory, $magazineFactory);
        }

        return $ret;
    }

    private function transformActor(array $actor, UserFactory $userFactory, MagazineFactory $magazineFactory): array
    {
        return match ($actor['type']) {
            'user' => [
                'type' => 'user',
                'object' => $this->serializeUser($userFactory->createDto($actor['object'])),
            ],
            'magazine' => [
                'type' => 'magazine',
                'object' => $this->serializeMagazine($magazineFactory->createDto($actor['object'])),
            ],
            default => throw new \LogicException('Unexpected actor type: '.$actor['type']),
        };
    }

    private function transformObjects(array $objects, UserFactory $userFactory, MagazineFactory $magazineFactory): array
    {
        $actors = [];
        $content = [];
        foreach ($objects as $object) {
            if ('user' === $object['type'] || 'magazine' === $object['type']) {
                $actors[] = $this->transformActor($object, $userFactory, $magazineFactory);
            } elseif ('subject' === $object['type']) {
                $subject = $object['object'];
                \assert($subject instanceof ContentInterface);
                $content[] = $this->serializeContentInterface($subject);
            } else {
                throw new \LogicException('Unexpected actor type: '.$object['type']);
            }
        }

        return [
            'actors' => $actors,
            'content' => $content,
        ];
    }
}
