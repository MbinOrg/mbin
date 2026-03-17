<?php

declare(strict_types=1);

namespace App\Controller\Api\Notification;

use App\Controller\Traits\PrivateContentTrait;
use App\DTO\NotificationPushSubscriptionRequestDto;
use App\Entity\UserPushSubscription;
use App\Payloads\PushNotification;
use App\Repository\UserPushSubscriptionRepository;
use App\Schema\Errors\ForbiddenErrorSchema;
use App\Schema\Errors\NotFoundErrorSchema;
use App\Schema\Errors\TooManyRequestsErrorSchema;
use App\Schema\Errors\UnauthorizedErrorSchema;
use App\Service\Notification\UserPushSubscriptionManager;
use App\Service\SettingsManager;
use Doctrine\DBAL\ParameterType;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class NotificationPushApi extends NotificationBaseApi
{
    use PrivateContentTrait;

    #[OA\Response(
        response: 200,
        description: 'Created a new push subscription. If there already is a push subscription for this client it will be overwritten. a test notification will be sent right away',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 403,
        description: 'You are not allowed to create push notifications',
        content: new OA\JsonContent(ref: new Model(type: ForbiddenErrorSchema::class))
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
    #[OA\RequestBody(content: new Model(type: NotificationPushSubscriptionRequestDto::class))]
    #[OA\Tag(name: 'notification')]
    #[Security(name: 'oauth2', scopes: ['user:notification:read'])]
    #[IsGranted('ROLE_OAUTH2_USER:NOTIFICATION:READ')]
    /**
     * Register a new push subscription.
     */
    public function createSubscription(
        RateLimiterFactoryInterface $apiNotificationLimiter,
        UserPushSubscriptionRepository $repository,
        SettingsManager $settingsManager,
        UserPushSubscriptionManager $pushSubscriptionManager,
        TranslatorInterface $translator,
        #[MapRequestPayload] NotificationPushSubscriptionRequestDto $payload,
    ): JsonResponse {
        $headers = $this->rateLimit($apiNotificationLimiter);
        $user = $this->getUserOrThrow();
        $token = $this->getOAuthToken();
        $apiToken = $this->getAccessToken($token);

        $pushSubscription = $repository->findOneBy(['user' => $user, 'apiToken' => $apiToken]);
        if (!$pushSubscription) {
            $pushSubscription = new UserPushSubscription($user, $payload->endpoint, $payload->contentPublicKey, $payload->serverKey, [], $apiToken);
            $pushSubscription->locale = $settingsManager->getLocale();
        } else {
            $pushSubscription->endpoint = $payload->endpoint;
            $pushSubscription->serverAuthKey = $payload->serverKey;
            $pushSubscription->contentEncryptionPublicKey = $payload->contentPublicKey;
        }

        $this->entityManager->persist($pushSubscription);
        $this->entityManager->flush();

        try {
            $testNotification = new PushNotification(null, '', $translator->trans('test_push_message', locale: $pushSubscription->locale));
            $pushSubscriptionManager->sendTextToUser($user, $testNotification, specificToken: $apiToken);

            return new JsonResponse(headers: $headers);
        } catch (\ErrorException $e) {
            $this->logger->error('There was an exception while deleting a UserPushSubscription: {e} - {m}. {o}', [
                'e' => \get_class($e),
                'm' => $e->getMessage(),
                'o' => json_encode($e),
            ]);

            return new JsonResponse(status: 500, headers: $headers);
        }
    }

    #[OA\Response(
        response: 200,
        description: 'Deleted the existing push subscription',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 403,
        description: 'You are not allowed to create push notifications',
        content: new OA\JsonContent(ref: new Model(type: ForbiddenErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Notification not found',
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
    #[OA\Tag(name: 'notification')]
    #[Security(name: 'oauth2', scopes: ['user:notification:read'])]
    #[IsGranted('ROLE_OAUTH2_USER:NOTIFICATION:READ')]
    /**
     * Delete the existing push subscription.
     */
    public function deleteSubscription(
        RateLimiterFactoryInterface $apiNotificationLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiNotificationLimiter);
        $user = $this->getUserOrThrow();
        $token = $this->getOAuthToken();
        $apiToken = $this->getAccessToken($token);

        try {
            $conn = $this->entityManager->getConnection();
            $stmt = $conn->prepare('DELETE FROM user_push_subscription WHERE user_id = :user AND api_token = :token');
            $stmt->bindValue('user', $user->getId(), ParameterType::INTEGER);
            $stmt->bindValue('token', $apiToken->getIdentifier());
            $stmt->executeQuery();

            return new JsonResponse(headers: $headers);
        } catch (\Exception $e) {
            $this->logger->error('There was an exception while deleting a UserPushSubscription: {e} - {m}. {o}', [
                'e' => \get_class($e),
                'm' => $e->getMessage(),
                'o' => json_encode($e),
            ]);

            return new JsonResponse(status: 500, headers: $headers);
        }
    }

    #[OA\Response(
        response: 200,
        description: 'A test notification should arrive shortly',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 403,
        description: 'You are not allowed to create push notifications',
        content: new OA\JsonContent(ref: new Model(type: ForbiddenErrorSchema::class))
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
    #[OA\Tag(name: 'notification')]
    #[Security(name: 'oauth2', scopes: ['user:notification:read'])]
    #[IsGranted('ROLE_OAUTH2_USER:NOTIFICATION:READ')]
    /**
     * Send a test push notification.
     */
    public function testSubscription(
        RateLimiterFactoryInterface $apiNotificationLimiter,
        UserPushSubscriptionRepository $repository,
        UserPushSubscriptionManager $pushSubscriptionManager,
        TranslatorInterface $translator,
    ): JsonResponse {
        $headers = $this->rateLimit($apiNotificationLimiter);
        $user = $this->getUserOrThrow();
        $token = $this->getOAuthToken();
        $apiToken = $this->getAccessToken($token);

        $sub = $repository->findOneBy(['user' => $user, 'apiToken' => $apiToken]);
        if ($sub) {
            $testNotification = new PushNotification(null, '', $translator->trans('test_push_message', locale: $sub->locale));
            try {
                $pushSubscriptionManager->sendTextToUser($user, $testNotification, specificToken: $apiToken);

                return new JsonResponse(headers: $headers);
            } catch (\ErrorException $e) {
                $this->logger->error('There was an exception while deleting a UserPushSubscription: {e} - {m}. {o}', [
                    'e' => \get_class($e),
                    'm' => $e->getMessage(),
                    'o' => json_encode($e),
                ]);

                return new JsonResponse(status: 500, headers: $headers);
            }
        } else {
            throw new BadRequestException(message: 'PushSubscription not found', statusCode: 404);
        }
    }
}
