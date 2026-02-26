<?php

declare(strict_types=1);

namespace App\Controller\Api\Magazine\Moderate;

use App\Controller\Api\Magazine\MagazineBaseApi;
use App\DTO\ToggleCreatedDto;
use App\Entity\Magazine;
use App\Entity\User;
use App\Security\Voter\MagazineVoter;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MagazineModOwnerRequestApi extends MagazineBaseApi
{
    #[OA\Response(
        response: 200,
        description: 'Moderator request created or deleted',
        content: new Model(type: ToggleCreatedDto::class),
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token or the magazine is not local',
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
        in: 'path',
        description: 'The magazine to apply for mod to',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Tag(name: 'moderation/magazine/owner')]
    #[Security(name: 'oauth2', scopes: ['magazine:subscribe'])]
    #[IsGranted('ROLE_OAUTH2_MAGAZINE:SUBSCRIBE')]
    #[IsGranted(MagazineVoter::SUBSCRIBE, subject: 'magazine')]
    public function toggleModRequest(
        #[MapEntity(id: 'magazine_id')]
        Magazine $magazine,
        RateLimiterFactory $apiModerateLimiter,
    ): Response {
        $headers = $this->rateLimit($apiModerateLimiter);

        // applying to be a moderator is only supported for local magazines
        if ($magazine->apId) {
            throw new AccessDeniedException();
        }

        $this->manager->toggleModeratorRequest($magazine, $this->getUserOrThrow());

        return new JsonResponse(
            new ToggleCreatedDto($this->manager->userRequestedModerator($magazine, $this->getUserOrThrow())),
            headers: $headers,
        );
    }

    #[OA\Response(
        response: 204,
        description: 'Moderator request was accepted',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token or you are no admin',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'mod request not found',
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
        in: 'path',
        description: 'The magazine to manage',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'user_id',
        in: 'path',
        description: 'The user to accept',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Tag(name: 'moderation/magazine/owner')]
    #[Security(name: 'oauth2', scopes: ['admin:magazine:moderate'])]
    #[IsGranted('ROLE_OAUTH2_ADMIN:MAGAZINE:MODERATE')]
    #[IsGranted('ROLE_ADMIN')]
    public function acceptModRequest(
        #[MapEntity(id: 'magazine_id')]
        Magazine $magazine,
        #[MapEntity(id: 'user_id')]
        User $user,
        RateLimiterFactory $apiModerateLimiter,
    ): Response {
        $headers = $this->rateLimit($apiModerateLimiter);

        if (!$this->manager->userRequestedModerator($magazine, $user)) {
            throw new NotFoundHttpException('moderator request does not exist');
        }

        $this->manager->acceptModeratorRequest($magazine, $user, $this->getUserOrThrow());

        return new Response(
            status: Response::HTTP_NO_CONTENT,
            headers: $headers,
        );
    }

    #[OA\Response(
        response: 204,
        description: 'Moderator request was rejected',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token or you are no admin',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'mod request not found',
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
        in: 'path',
        description: 'The magazine to manage',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'user_id',
        in: 'path',
        description: 'The user to reject',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Tag(name: 'moderation/magazine/owner')]
    #[Security(name: 'oauth2', scopes: ['admin:magazine:moderate'])]
    #[IsGranted('ROLE_OAUTH2_ADMIN:MAGAZINE:MODERATE')]
    #[IsGranted('ROLE_ADMIN')]
    public function rejectModRequest(
        #[MapEntity(id: 'magazine_id')]
        Magazine $magazine,
        #[MapEntity(id: 'user_id')]
        User $user,
        RateLimiterFactory $apiModerateLimiter,
    ): Response {
        $headers = $this->rateLimit($apiModerateLimiter);

        if (!$this->manager->userRequestedModerator($magazine, $user)) {
            throw new NotFoundHttpException('moderator request does not exist');
        }

        $this->manager->toggleModeratorRequest($magazine, $user);

        return new Response(
            status: Response::HTTP_NO_CONTENT,
            headers: $headers,
        );
    }

    #[OA\Response(
        response: 200,
        description: 'returns a list of moderator requests with user and magazine',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token or you are no admin',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Magazine not found or id was invalid',
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
        name: 'magazine',
        in: 'query',
        description: 'The magazine to filter for',
        required: false,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Tag(name: 'moderation/magazine/owner')]
    #[Security(name: 'oauth2', scopes: ['admin:magazine:moderate'])]
    #[IsGranted('ROLE_OAUTH2_ADMIN:MAGAZINE:MODERATE')]
    #[IsGranted('ROLE_ADMIN')]
    public function getModRequests(
        #[MapQueryParameter(name: 'magazine')]
        ?int $magazineId,
        RateLimiterFactory $apiModerateLimiter,
    ): Response {
        $headers = $this->rateLimit($apiModerateLimiter);

        $magazine = null;
        if (null !== $magazineId) {
            $magazine = $this->entityManager->getRepository(Magazine::class)->find($magazineId);

            if (null === $magazine) {
                throw new NotFoundHttpException("magazine with id $magazineId does not exist");
            }
        }

        $requests = $this->manager->listModeratorRequests($magazine);
        $requestsDto = array_map(function ($item) {
            return [
                'user' => $this->userFactory->createSmallDto($item->user),
                'magazine' => $this->magazineFactory->createSmallDto($item->magazine),
            ];
        }, $requests);

        return new JsonResponse(
            $requestsDto,
            headers: $headers,
        );
    }

    #[OA\Response(
        response: 200,
        description: 'Owner request created or deleted',
        content: new Model(type: ToggleCreatedDto::class),
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token or the magazine is not local',
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
        in: 'path',
        description: 'The magazine to apply for owner to',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Tag(name: 'moderation/magazine/owner')]
    #[Security(name: 'oauth2', scopes: ['magazine:subscribe'])]
    #[IsGranted('ROLE_OAUTH2_MAGAZINE:SUBSCRIBE')]
    #[IsGranted(MagazineVoter::SUBSCRIBE, subject: 'magazine')]
    public function toggleOwnerRequest(
        #[MapEntity(id: 'magazine_id')]
        Magazine $magazine,
        RateLimiterFactory $apiModerateLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiModerateLimiter);

        // applying to be a owner is only supported for local magazines
        if ($magazine->apId) {
            throw new AccessDeniedException();
        }

        $this->manager->toggleOwnershipRequest($magazine, $this->getUserOrThrow());

        return new JsonResponse(
            new ToggleCreatedDto($this->manager->userRequestedOwnership($magazine, $this->getUserOrThrow())),
            headers: $headers,
        );
    }

    #[OA\Response(
        response: 204,
        description: 'Ownership request was accepted',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token or you are no admin',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'owner request not found',
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
        in: 'path',
        description: 'The magazine to manage',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'user_id',
        in: 'path',
        description: 'The user to reject',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Tag(name: 'moderation/magazine/owner')]
    #[Security(name: 'oauth2', scopes: ['admin:magazine:moderate'])]
    #[IsGranted('ROLE_OAUTH2_ADMIN:MAGAZINE:MODERATE')]
    #[IsGranted('ROLE_ADMIN')]
    public function acceptOwnerRequest(
        #[MapEntity(id: 'magazine_id')]
        Magazine $magazine,
        #[MapEntity(id: 'user_id')]
        User $user,
        RateLimiterFactory $apiModerateLimiter,
    ): Response {
        $headers = $this->rateLimit($apiModerateLimiter);

        if (!$this->manager->userRequestedOwnership($magazine, $user)) {
            throw new NotFoundHttpException('ownership request does not exist');
        }

        $this->manager->acceptOwnershipRequest($magazine, $user, $this->getUserOrThrow());

        return new JsonResponse(
            status: Response::HTTP_NO_CONTENT,
            headers: $headers,
        );
    }

    #[OA\Response(
        response: 204,
        description: 'Moderator request was rejected',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token or you are no admin',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'owner request not found',
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
        in: 'path',
        description: 'The magazine to manage',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'user_id',
        in: 'path',
        description: 'The user to reject',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Tag(name: 'moderation/magazine/owner')]
    #[Security(name: 'oauth2', scopes: ['admin:magazine:moderate'])]
    #[IsGranted('ROLE_OAUTH2_ADMIN:MAGAZINE:MODERATE')]
    #[IsGranted('ROLE_ADMIN')]
    public function rejectOwnerRequest(
        #[MapEntity(id: 'magazine_id')]
        Magazine $magazine,
        #[MapEntity(id: 'user_id')]
        User $user,
        RateLimiterFactory $apiModerateLimiter,
    ): Response {
        $headers = $this->rateLimit($apiModerateLimiter);

        if (!$this->manager->userRequestedOwnership($magazine, $user)) {
            throw new NotFoundHttpException('ownership request does not exist');
        }

        $this->manager->toggleOwnershipRequest($magazine, $user);

        return new Response(
            status: Response::HTTP_NO_CONTENT,
            headers: $headers,
        );
    }

    #[OA\Response(
        response: 200,
        description: 'returns a list of ownership requests with user and magazine',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token or you are no admin',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Magazine not found or id was invalid',
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
        name: 'magazine',
        in: 'query',
        description: 'The magazine filter for',
        required: false,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Tag(name: 'moderation/magazine/owner')]
    #[Security(name: 'oauth2', scopes: ['admin:magazine:moderate'])]
    #[IsGranted('ROLE_OAUTH2_ADMIN:MAGAZINE:MODERATE')]
    #[IsGranted('ROLE_ADMIN')]
    public function getOwnerRequests(
        #[MapQueryParameter(name: 'magazine')]
        ?int $magazineId,
        RateLimiterFactory $apiModerateLimiter,
    ): Response {
        $headers = $this->rateLimit($apiModerateLimiter);

        $magazine = null;
        if (null !== $magazineId) {
            $magazine = $this->entityManager->getRepository(Magazine::class)->find($magazineId);

            if (null === $magazine) {
                throw new NotFoundHttpException("magazine with id $magazineId does not exist");
            }
        }

        $requests = $this->manager->listOwnershipRequests($magazine);
        $requestsDto = array_map(function ($item) {
            return [
                'user' => $this->userFactory->createSmallDto($item->user),
                'magazine' => $this->magazineFactory->createSmallDto($item->magazine),
            ];
        }, $requests);

        return new JsonResponse(
            $requestsDto,
            headers: $headers,
        );
    }
}
