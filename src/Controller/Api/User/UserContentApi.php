<?php

declare(strict_types=1);

namespace App\Controller\Api\User;

use App\Controller\Traits\PrivateContentTrait;
use App\DTO\ExtendedContentResponseDto;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Repository\SearchRepository;
use App\Schema\Errors\TooManyRequestsErrorSchema;
use App\Schema\Errors\UnauthorizedErrorSchema;
use App\Schema\PaginationSchema;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

class UserContentApi extends UserBaseApi
{
    use PrivateContentTrait;

    #[OA\Response(
        response: 200,
        description: 'A paginated list of combined entries, posts, comments and replies boosted by the given user',
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
                    items: new OA\Items(ref: new Model(type: ExtendedContentResponseDto::class))
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
        description: 'user not found or you are not allowed to access them',
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
    #[OA\Tag(name: 'user')]
    public function getBoostedContent(
        #[MapEntity(id: 'user_id')]
        User $user,
        #[MapQueryParameter]
        ?int $p,
        SearchRepository $repository,
        RateLimiterFactoryInterface $apiReadLimiter,
        RateLimiterFactoryInterface $anonymousApiReadLimiter,
    ): Response {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $this->checkUserAccess($user);

        $search = $repository->findBoosts($p ?? 1, $user);
        $result = $this->serializeResults($search->getCurrentPageResults());

        return new JsonResponse(
            $this->serializePaginated($result, $search),
            headers: $headers
        );
    }

    #[OA\Response(
        response: 200,
        description: 'A paginated list of combined entries, posts, comments and replies boosted by the given user',
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
                    items: new OA\Items(ref: new Model(type: ExtendedContentResponseDto::class))
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
        description: 'user not found or you are not allowed to access them',
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
        name: 'hideAdult',
        description: 'If true exclude all adult content',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false)
    )]
    #[OA\Parameter(
        name: 'p',
        description: 'Page of content to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
    )]
    #[OA\Tag(name: 'user')]
    public function getUserContent(
        #[MapEntity(id: 'user_id')]
        User $user,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_BOOLEAN)]
        ?bool $hideAdult,
        #[MapQueryParameter]
        ?int $p,
        SearchRepository $repository,
        RateLimiterFactoryInterface $apiReadLimiter,
        RateLimiterFactoryInterface $anonymousApiReadLimiter,
    ): Response {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $this->checkUserAccess($user);

        $search = $repository->findUserPublicActivity($p ?? 1, $user, $hideAdult ?? false);
        $result = $this->serializeResults($search->getCurrentPageResults());

        return new JsonResponse(
            $this->serializePaginated($result, $search),
            headers: $headers
        );
    }

    private function checkUserAccess(User $user)
    {
        $requestingUser = $this->getUser();
        if ($user->isDeleted && (!$requestingUser || (!$requestingUser->isAdmin() && !$requestingUser->isModerator()) || null === $user->markedForDeletionAt)) {
            throw $this->createNotFoundException();
        }
    }

    private function serializeResults(array $results): array
    {
        $result = [];
        foreach ($results as $item) {
            try {
                if ($item instanceof Entry) {
                    $this->handlePrivateContent($item);
                    $result[] = new ExtendedContentResponseDto(entry: $this->serializeEntry($this->entryFactory->createDto($item), $this->tagLinkRepository->getTagsOfContent($item)));
                } elseif ($item instanceof Post) {
                    $this->handlePrivateContent($item);
                    $result[] = new ExtendedContentResponseDto(post: $this->serializePost($this->postFactory->createDto($item), $this->tagLinkRepository->getTagsOfContent($item)));
                } elseif ($item instanceof EntryComment) {
                    $this->handlePrivateContent($item);
                    $result[] = new ExtendedContentResponseDto(entryComment: $this->serializeEntryComment($this->entryCommentFactory->createDto($item), $this->tagLinkRepository->getTagsOfContent($item)));
                } elseif ($item instanceof PostComment) {
                    $this->handlePrivateContent($item);
                    $result[] = new ExtendedContentResponseDto(postComment: $this->serializePostComment($this->postCommentFactory->createDto($item), $this->tagLinkRepository->getTagsOfContent($item)));
                }
            } catch (\Exception) {
            }
        }

        return $result;
    }
}
