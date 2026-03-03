<?php

declare(strict_types=1);

namespace App\Controller\Api\Instance\Admin;

use App\Controller\Api\Instance\InstanceBaseApi;
use App\DTO\InstancesDto;
use App\DTO\SettingsDto;
use App\Entity\Instance;
use App\Repository\InstanceRepository;
use App\Schema\Errors\BadRequestErrorSchema;
use App\Schema\Errors\ForbiddenErrorSchema;
use App\Schema\Errors\NotFoundErrorSchema;
use App\Schema\Errors\TooManyRequestsErrorSchema;
use App\Schema\Errors\UnauthorizedErrorSchema;
use App\Service\InstanceManager;
use App\Service\SettingsManager;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class InstanceUpdateFederationApi extends InstanceBaseApi
{
    #[OA\Response(
        response: 200,
        description: 'Defederated instances updated',
        content: new Model(type: InstancesDto::class),
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Response(
        response: 400,
        description: 'One of the URLs entered was invalid',
        content: new OA\JsonContent(ref: new Model(type: BadRequestErrorSchema::class))
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 403,
        description: 'You do not have permission to edit the list of defederated instances',
        content: new OA\JsonContent(ref: new Model(type: ForbiddenErrorSchema::class))
    )]
    #[OA\Response(
        response: 429,
        description: 'You are being rate limited',
        content: new OA\JsonContent(ref: new Model(type: TooManyRequestsErrorSchema::class)),
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\RequestBody(content: new Model(type: InstancesDto::class))]
    #[OA\Tag(name: 'admin/federation')]
    #[IsGranted('ROLE_ADMIN')]
    #[Security(name: 'oauth2', scopes: ['admin:federation:update'])]
    #[IsGranted('ROLE_OAUTH2_ADMIN:FEDERATION:UPDATE')]
    #[OA\Put(description: 'This is the old version of banning and unbanning instances, use /api/instance/ban and /api/instance/unban instead', deprecated: true)]
    public function __invoke(
        SettingsManager $settings,
        InstanceRepository $instanceRepository,
        InstanceManager $instanceManager,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        RateLimiterFactoryInterface $apiModerateLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiModerateLimiter);

        $request = $this->request->getCurrentRequest();
        /** @var InstancesDto $dto */
        $dto = $serializer->deserialize($request->getContent(), InstancesDto::class, 'json');

        $dto->instances = array_map(
            fn (string $instance) => trim(str_replace('www.', '', $instance)),
            $dto->instances
        );

        $errors = $validator->validate($dto);
        if (0 < \count($errors)) {
            throw new BadRequestHttpException((string) $errors);
        }

        $instanceManager->setBannedInstances($dto->instances);

        $dto = new InstancesDto($instanceRepository->getBannedInstanceUrls());

        return new JsonResponse(
            $dto,
            headers: $headers
        );
    }

    #[OA\Response(
        response: 200,
        description: 'Instance added to ban list',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new Model(type: SettingsDto::class)
    )]
    #[OA\Response(
        response: 400,
        description: 'Instance cannot be banned when an allow list is used',
        content: new OA\JsonContent(ref: new Model(type: BadRequestErrorSchema::class))
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 403,
        description: 'You do not have permission to edit the instance settings',
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
    #[OA\Tag(name: 'admin/federation')]
    #[IsGranted('ROLE_ADMIN')]
    #[Security(name: 'oauth2', scopes: ['admin:federation:update'])]
    #[IsGranted('ROLE_OAUTH2_ADMIN:FEDERATION:UPDATE')]
    public function banInstance(
        RateLimiterFactoryInterface $apiModerateLimiter,
        string $domain,
    ): JsonResponse {
        $headers = $this->rateLimit($apiModerateLimiter);
        $instance = $this->instanceRepository->getOrCreateInstance($domain);
        try {
            $this->instanceManager->banInstance($instance);
        } catch (\LogicException $exception) {
            throw new BadRequestHttpException($exception->getMessage());
        }

        return new JsonResponse(headers: $headers);
    }

    #[OA\Response(
        response: 200,
        description: 'Instance removed from ban list',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new Model(type: SettingsDto::class)
    )]
    #[OA\Response(
        response: 400,
        description: 'Instance cannot be unbanned when an allow list is used',
        content: new OA\JsonContent(ref: new Model(type: BadRequestErrorSchema::class))
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 403,
        description: 'You do not have permission to edit the instance settings',
        content: new OA\JsonContent(ref: new Model(type: ForbiddenErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Instance not found',
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
    #[OA\Tag(name: 'admin/federation')]
    #[IsGranted('ROLE_ADMIN')]
    #[Security(name: 'oauth2', scopes: ['admin:federation:update'])]
    #[IsGranted('ROLE_OAUTH2_ADMIN:FEDERATION:UPDATE')]
    public function unbanInstance(
        RateLimiterFactoryInterface $apiModerateLimiter,
        #[MapEntity(mapping: ['domain' => 'domain'])] Instance $instance,
    ): JsonResponse {
        $headers = $this->rateLimit($apiModerateLimiter);
        try {
            $this->instanceManager->unbanInstance($instance);
        } catch (\LogicException $exception) {
            throw new BadRequestHttpException($exception->getMessage());
        }

        return new JsonResponse(headers: $headers);
    }

    #[OA\Response(
        response: 200,
        description: 'Instance added to allowlist',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new Model(type: SettingsDto::class)
    )]
    #[OA\Response(
        response: 400,
        description: 'Instance cannot be removed from the allow list when the allow list is not used',
        content: new OA\JsonContent(ref: new Model(type: BadRequestErrorSchema::class))
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 403,
        description: 'You do not have permission to edit the instance settings',
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
    #[OA\Tag(name: 'admin/federation')]
    #[IsGranted('ROLE_ADMIN')]
    #[Security(name: 'oauth2', scopes: ['admin:federation:update'])]
    #[IsGranted('ROLE_OAUTH2_ADMIN:FEDERATION:UPDATE')]
    public function allowInstance(
        RateLimiterFactoryInterface $apiModerateLimiter,
        string $domain,
    ): JsonResponse {
        $headers = $this->rateLimit($apiModerateLimiter);
        $instance = $this->instanceRepository->getOrCreateInstance($domain);
        try {
            $this->instanceManager->allowInstanceFederation($instance);
        } catch (\LogicException $exception) {
            throw new BadRequestHttpException($exception->getMessage());
        }

        return new JsonResponse(headers: $headers);
    }

    #[OA\Response(
        response: 200,
        description: 'Instance removed from allow list',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new Model(type: SettingsDto::class)
    )]
    #[OA\Response(
        response: 400,
        description: 'Instance cannot be put on the allow list when the allow list is not used',
        content: new OA\JsonContent(ref: new Model(type: BadRequestErrorSchema::class))
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 403,
        description: 'You do not have permission to edit the instance settings',
        content: new OA\JsonContent(ref: new Model(type: ForbiddenErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Instance not found',
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
    #[OA\Tag(name: 'admin/federation')]
    #[IsGranted('ROLE_ADMIN')]
    #[Security(name: 'oauth2', scopes: ['admin:federation:update'])]
    #[IsGranted('ROLE_OAUTH2_ADMIN:FEDERATION:UPDATE')]
    public function denyInstance(
        RateLimiterFactoryInterface $apiModerateLimiter,
        #[MapEntity(mapping: ['domain' => 'domain'])] Instance $instance,
    ): JsonResponse {
        $headers = $this->rateLimit($apiModerateLimiter);
        try {
            $this->instanceManager->denyInstanceFederation($instance);
        } catch (\LogicException $exception) {
            throw new BadRequestHttpException($exception->getMessage());
        }

        return new JsonResponse(headers: $headers);
    }
}
