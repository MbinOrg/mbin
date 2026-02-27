<?php

declare(strict_types=1);

namespace App\Controller\Api\Notification;

use App\Controller\Traits\PrivateContentTrait;
use App\Entity\Entry;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Entity\User;
use App\Enums\ENotificationStatus;
use App\Schema\Errors\NotFoundErrorSchema;
use App\Schema\Errors\TooManyRequestsErrorSchema;
use App\Schema\Errors\UnauthorizedErrorSchema;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class NotificationSettingApi extends NotificationBaseApi
{
    use PrivateContentTrait;

    #[OA\Response(
        response: 204,
        description: 'Updated the notification status',
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
        description: 'Target not found',
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
        name: 'target_id',
        description: 'The id of the target',
        in: 'path',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'target_type',
        description: 'The type of the target',
        in: 'path',
        schema: new OA\Schema(enum: ['entry', 'post', 'magazine', 'user']),
    )]
    #[OA\Parameter(
        name: 'setting',
        description: 'The new notification setting',
        in: 'path',
        schema: new OA\Schema(enum: ENotificationStatus::Values),
    )]
    #[OA\Tag(name: 'notification')]
    #[Security(name: 'oauth2', scopes: ['user:notification:edit'])]
    #[IsGranted('ROLE_OAUTH2_USER:NOTIFICATION:EDIT')]
    public function update(
        string $targetType,
        int $targetId,
        string $setting,
        RateLimiterFactoryInterface $apiUpdateLimiter,
    ): JsonResponse {
        $this->rateLimit($apiUpdateLimiter);
        $user = $this->getUserOrThrow();
        $notificationSetting = ENotificationStatus::getFromString($setting);
        if (null === $notificationSetting) {
            throw $this->createNotFoundException('setting does not exist');
        }

        if ('entry' === $targetType) {
            $repo = $this->entityManager->getRepository(Entry::class);
        } elseif ('post' === $targetType) {
            $repo = $this->entityManager->getRepository(Post::class);
        } elseif ('magazine' === $targetType) {
            $repo = $this->entityManager->getRepository(Magazine::class);
        } elseif ('user' === $targetType) {
            $repo = $this->entityManager->getRepository(User::class);
        } else {
            throw new \LogicException();
        }
        $target = $repo->find($targetId);
        if (null === $target) {
            throw $this->createNotFoundException();
        }
        $this->notificationSettingsRepository->setStatusByTarget($user, $target, $notificationSetting);

        return new JsonResponse();
    }
}
