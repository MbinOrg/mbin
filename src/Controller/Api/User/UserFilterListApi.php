<?php

declare(strict_types=1);

namespace App\Controller\Api\User;

use App\DTO\UserFilterListDto;
use App\Entity\UserFilterList;
use App\Schema\Errors\TooManyRequestsErrorSchema;
use App\Schema\Errors\UnauthorizedErrorSchema;
use App\Security\Voter\FilterListVoter;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserFilterListApi extends UserBaseApi
{
    #[OA\Response(
        response: 200,
        description: 'The List',
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
                    items: new OA\Items(ref: new Model(type: UserFilterListDto::class))
                ),
            ]
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
    #[OA\Tag(name: 'user')]
    #[Security(name: 'oauth2', scopes: ['user:profile:edit'])]
    #[IsGranted('ROLE_OAUTH2_USER:PROFILE:EDIT')]
    public function retrieve(): JsonResponse
    {
        $user = $this->getUserOrThrow();
        $items = [];
        foreach ($user->filterLists as $list) {
            $items[] = $this->serializeFilterList($list);
        }

        return new JsonResponse([
            'items' => $items,
        ]);
    }

    #[OA\Response(
        response: 200,
        description: 'Filter list created',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new Model(type: UserFilterListDto::class)
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
    #[OA\RequestBody(ref: new Model(type: UserFilterListDto::class))]
    #[OA\Tag(name: 'user')]
    #[Security(name: 'oauth2', scopes: ['user:profile:edit'])]
    #[IsGranted('ROLE_OAUTH2_USER:PROFILE:EDIT')]
    public function create(
        #[MapRequestPayload] UserFilterListDto $dto,
        RateLimiterFactoryInterface $apiUpdateLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiUpdateLimiter);

        $user = $this->getUserOrThrow();
        $list = new UserFilterList();
        $list->name = $dto->name;
        $list->expirationDate = $dto->expirationDate;
        $list->feeds = $dto->feeds;
        $list->comments = $dto->comments;
        $list->profile = $dto->profile;
        $list->user = $user;
        $list->words = $dto->wordsToArray();
        $this->entityManager->persist($list);
        $this->entityManager->flush();

        $freshList = $this->entityManager->getRepository(UserFilterList::class)->find($list->getId());

        return new JsonResponse(
            $this->serializeFilterList($freshList),
            headers: $headers
        );
    }

    #[OA\Response(
        response: 200,
        description: 'Filter list updated',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new Model(type: UserFilterListDto::class)
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
    #[OA\RequestBody(ref: new Model(type: UserFilterListDto::class))]
    #[OA\Tag(name: 'user')]
    #[Security(name: 'oauth2', scopes: ['user:profile:edit'])]
    #[IsGranted('ROLE_OAUTH2_USER:PROFILE:EDIT')]
    #[IsGranted(FilterListVoter::EDIT, 'list')]
    public function edit(
        RateLimiterFactoryInterface $apiUpdateLimiter,
        #[MapEntity(class: UserFilterList::class)] UserFilterList $list,
        #[MapRequestPayload] UserFilterListDto $dto,
    ): JsonResponse {
        $headers = $this->rateLimit($apiUpdateLimiter);
        $list->name = $dto->name;
        $list->expirationDate = $dto->expirationDate;
        $list->feeds = $dto->feeds;
        $list->comments = $dto->comments;
        $list->profile = $dto->profile;
        $list->words = $dto->wordsToArray();

        $this->entityManager->persist($list);
        $this->entityManager->flush();
        $freshList = $this->entityManager->getRepository(UserFilterList::class)->find($list->getId());

        return new JsonResponse(
            $this->serializeFilterList($freshList),
            headers: $headers
        );
    }

    #[OA\Response(
        response: 200,
        description: 'Filter list deleted',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
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
    #[OA\Tag(name: 'user')]
    #[Security(name: 'oauth2', scopes: ['user:profile:edit'])]
    #[IsGranted('ROLE_OAUTH2_USER:PROFILE:EDIT')]
    #[IsGranted(FilterListVoter::DELETE, 'list')]
    public function delete(
        RateLimiterFactoryInterface $apiDeleteLimiter,
        #[MapEntity(class: UserFilterList::class)] UserFilterList $list,
    ): JsonResponse {
        $headers = $this->rateLimit($apiDeleteLimiter);

        $this->entityManager->remove($list);
        $this->entityManager->flush();

        return new JsonResponse(
            headers: $headers
        );
    }
}
