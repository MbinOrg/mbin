<?php

declare(strict_types=1);

namespace App\Controller\Api\Post;

use App\Controller\Traits\PrivateContentTrait;
use App\DTO\PostResponseDto;
use App\Entity\Post;
use App\Entity\User;
use App\Factory\PostFactory;
use App\PageView\PostPageView;
use App\Repository\Criteria;
use App\Repository\PostRepository;
use App\Schema\PaginationSchema;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class UserPostsRetrieveApi extends PostsBaseApi
{
    use PrivateContentTrait;

    #[OA\Response(
        response: 200,
        description: 'A paginated list of posts from the user',
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
        description: 'User not found',
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
        name: 'user_id',
        description: 'User whose posts to retrieve',
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
    #[OA\Tag(name: 'user')]
    public function __invoke(
        #[MapEntity(id: 'user_id')]
        User $user,
        PostRepository $repository,
        PostFactory $factory,
        RequestStack $request,
        RateLimiterFactory $apiReadLimiter,
        RateLimiterFactory $anonymousApiReadLimiter
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $criteria = new PostPageView((int) $request->getCurrentRequest()->get('p', 1));
        $criteria->sortOption = $request->getCurrentRequest()->get('sort', Criteria::SORT_HOT);
        $criteria->time = $criteria->resolveTime(
            $request->getCurrentRequest()->get('time', Criteria::TIME_ALL)
        );
        $criteria->perPage = self::constrainPerPage($request->getCurrentRequest()->get('perPage', PostRepository::PER_PAGE));

        $this->handleLanguageCriteria($criteria);

        $criteria->user = $user;

        $posts = $repository->findByCriteria($criteria);

        $dtos = [];
        foreach ($posts->getCurrentPageResults() as $value) {
            try {
                \assert($value instanceof Post);
                $this->handlePrivateContent($value);
                array_push($dtos, $this->serializePost($factory->createDto($value), $this->tagLinkRepository->getTagsOfPost($value)));
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
