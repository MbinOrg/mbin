<?php

declare(strict_types=1);

namespace App\Controller\Api\OAuth2;

use App\Controller\Api\BaseApi;
use App\DTO\ImageUploadDto;
use App\DTO\OAuth2ClientDto;
use App\DTO\UserDto;
use App\Entity\Client;
use App\Entity\Image;
use App\Entity\User;
use App\Factory\ClientFactory;
use App\Repository\UserRepository;
use App\Service\SettingsManager;
use App\Service\UserManager;
use App\Utils\Polyfills;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Service\Attribute\Required;

class CreateClientApi extends BaseApi
{

    private ?PasswordHasherInterface $passwordHasher = null;
    private ClientManagerInterface $manager;
    private ClientFactory $clientFactory;

    #[Required]
    public function setClientManager(ClientManagerInterface $clientManager): void
    {
        $this->manager = $clientManager;
    }

    #[Required]
    public function setClientFactory(ClientFactory $clientFactory): void {
        $this->clientFactory = $clientFactory;
    }

    #[OA\Response(
        response: 201,
        description: 'Returns the created oauth2 client. Be sure to save the identifier and secret since these will be how you obtain tokens for the API.',
        content: new Model(type: OAuth2ClientDto::class, groups: ['created', 'common']),
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Response(
        response: 400,
        description: 'Grant type(s), scope(s), redirectUri(s) were invalid, or username was taken',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\BadRequestErrorSchema::class))
    )]
    #[OA\Response(
        response: 403,
        description: 'This instance only allows admins to create clients',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\ForbiddenErrorSchema::class))
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
    #[OA\RequestBody(content: new Model(
        type: OAuth2ClientDto::class,
        groups: ['creating']
    ))]
    #[OA\Tag(name: 'oauth')]
    /**
     * This endpoint can create an OAuth2 client for your application.
     *
     * You can create a public or confidential client with any of 3 flows available. It's
     * recommended that you pick **either** `client_credentials`, **or** `authorization_code` *and* `refresh_token`.
     *
     * When creating clients with the client_credentials grant type, you must provide a unique
     * username and contact email. The username and email will be used to create a new bot user,
     * which your client authenticates as during the client_credentials flow. This user will be
     * tagged as a bot on all of their posts, comments, and on their profile. In addition, the bot
     * will not be allowed to use the API to vote on content.
     *
     * If you are creating a client that will be used on a native app or webapp, the client
     * should be marked as public. This will skip generation of a client secret and will require
     * the client to use the PKCE (https://www.oauth.com/oauth2-servers/pkce/) extension during
     * authorization_code flow. A public client cannot use the client_credentials flow. Public clients
     * are recommended because apps running on user devices technically cannot store secrets safely -
     * if they're determined enough, the user could retrieve the secret from their device's memory.
     *
     *  If you are creating a private client you have to provide a secret on client-registration and when calling /token.
     *  It is recommended to create it as a hex-string of at least 128 bits of randomness.
     */
    public function __invoke(
        RateLimiterFactoryInterface $apiOauthClientLimiter,
    ): JsonResponse {
        $this->checkAvailable();

        $headers = $this->rateLimit($apiOauthClientLimiter, $apiOauthClientLimiter);

        $request = $this->request->getCurrentRequest();
        /** @var OAuth2ClientDto $dto */
        $reqDto = $this->serializer->deserialize($request->getContent(), OAuth2ClientDto::class, 'json', ['groups' => ['creating']]);
        $this->validateRequest($reqDto);

        $client = $this->createClient($reqDto, null);
        $respDto = $this->clientFactory->createDto($client);

        return new JsonResponse(
            $respDto,
            status: 201,
            headers: $headers
        );
    }

    #[OA\Response(
        response: 201,
        description: 'Returns the created oauth2 client. Be sure to save the identifier and secret since these will be how you obtain tokens for the API.',
        content: new Model(type: OAuth2ClientDto::class, groups: ['Default', 'created', 'common']),
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Response(
        response: 400,
        description: 'Grant type(s), scope(s), redirectUri(s) were invalid, or username/email was taken',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\BadRequestErrorSchema::class))
    )]
    #[OA\Response(
        response: 403,
        description: 'This instance only allows admins to create clients',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\ForbiddenErrorSchema::class))
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
    #[OA\RequestBody(content: new OA\MediaType(
        'multipart/form-data',
        schema: new OA\Schema(
            ref: new Model(
                type: OAuth2ClientDto::class,
                groups: [
                    'creating',
                    ImageUploadDto::IMAGE_UPLOAD_NO_ALT,
                ]
            )
        )
    ))]
    #[OA\Tag(name: 'oauth')]
    /**
     * This endpoint can create an OAuth2 client with a logo for your application.
     *
     * The image uploaded to this endpoint will be shown to users on the consent page as your application's logo.
     *
     * You can create a public or confidential client with any of 3 flows available. It's
     * recommended that you pick **either** `client_credentials`, **or** `authorization_code` *and* `refresh_token`.
     *
     * When creating clients with the client_credentials grant type, you must provide a unique
     * username and contact email. The username and email will be used to create a new bot user,
     * which your client authenticates as during the client_credentials flow. This user will be
     * tagged as a bot on all of their posts, comments, and on their profile. In addition, the bot
     * will not be allowed to use the API to vote on content.
     *
     * If you are creating a client that will be used on a native app or webapp, the client
     * should be marked as public. This will skip generation of a client secret and will require
     * the client to use the PKCE (https://www.oauth.com/oauth2-servers/pkce/) extension during
     * authorization_code flow. A public client cannot use the client_credentials flow. Public clients
     * are recommended because apps running on user devices technically cannot store secrets safely -
     * if they're determined enough, the user could retrieve the secret from their device's memory.
     *
     * If you are creating a private client you have to provide a secret on client-registration and when calling /token.
     * It is recommended to create it as a hex-string of at least 128 bits of randomness.
     */
    public function uploadImage(
        RateLimiterFactoryInterface $apiOauthClientLimiter,
    ): JsonResponse {
        $this->checkAvailable();

        $headers = $this->rateLimit($apiOauthClientLimiter, $apiOauthClientLimiter);

        $image = $this->handleUploadedImage();
        $reqDto = $this->deserializeClientFromForm();
        $this->validateRequest($reqDto);

        $client = $this->createClient($reqDto, $image);
        $respDto = $this->clientFactory->createDto($client);

        return new JsonResponse(
            $respDto,
            status: 201,
            headers: $headers
        );
    }

    private function checkAvailable(): void
    {
        if ($this->settingsManager->get('KBIN_ADMIN_ONLY_OAUTH_CLIENTS') && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedHttpException('This instance only allows admins to create oauth clients');
        }
    }

    private function deserializeClientFromForm(): OAuth2ClientDto
    {
        $request = $this->request->getCurrentRequest();
        $dto = new OAuth2ClientDto();
        $dto->name = Polyfills::requestParam($request, 'name', $dto->name);
        $dto->contactEmail = Polyfills::requestParam($request, 'contactEmail', $dto->contactEmail);
        $dto->username = Polyfills::requestParam($request, 'username', $dto->username);
        $dto->description = Polyfills::requestParam($request, 'description', $dto->description);
        $dto->public = filter_var(Polyfills::requestParam($request, 'public', $dto->public), FILTER_VALIDATE_BOOL);
        $dto->description = Polyfills::requestParam($request, 'secret', $dto->secret);

        $redirectUris = Polyfills::requestParam($request, 'redirectUris', $dto->redirectUris);
        if (\is_string($redirectUris)) {
            $redirectUris = preg_split('/(,| )/', $redirectUris, flags: PREG_SPLIT_NO_EMPTY);
        }
        $dto->redirectUris = $redirectUris;

        $grants = Polyfills::requestParam($request, 'grants', $dto->grants);
        if (\is_string($grants)) {
            $grants = preg_split('/(,| )/', $grants, flags: PREG_SPLIT_NO_EMPTY);
        }
        $dto->grants = $grants;

        $scopes = Polyfills::requestParam($request, 'scopes', $dto->scopes);
        if (\is_string($scopes)) {
            $scopes = preg_split('/(,| )/', $scopes, flags: PREG_SPLIT_NO_EMPTY);
        }
        $dto->scopes = $scopes;

        return $dto;
    }

    private function validateRequest(OAuth2ClientDto $dto): void {
        $validatorGroups = ['Default', 'creating'];
        // If the client being requested wishes to use the client_credentials flow,
        //   validate that it has a username.
        if (in_array('client_credentials', $dto->grants)) {
            $validatorGroups[] = 'client_credentials';
        }

        $errors = $this->validator->validate($dto, groups: $validatorGroups);
        if (0 < \count($errors)) {
            throw new BadRequestHttpException((string) $errors);
        }
    }

    private function createClient(OAuth2ClientDto $dto, ?Image $image): Client
    {
        $secret = $dto->secret;
        if($dto->public !== true) {
            if($secret === null) {
                throw new BadRequestHttpException('Non-public clients require a secret');
            }
            if(strlen($secret) < 32) {
                throw new BadRequestHttpException('The secret has a low entropy');
            }

            $secret = $this->getPasswordHasher()->hash($secret);
        } else {
            if($secret !== null) {
                throw new BadRequestHttpException('Public clients must not use a secret');
            }
        }

        $identifier = hash('sha256', random_bytes(256));
        $client = new Client($dto->name, $identifier, $secret);

        if (in_array('client_credentials', $dto->grants)) {
            $user = $this->createClientUser($dto);
            $client->setUser($user);
        }
        $client->setDescription($dto->description);
        $client->setContactEmail($dto->contactEmail);
        $client->setGrants(...array_map(fn (string $grant) => new Grant($grant), $dto->grants));
        $client->setScopes(...array_map(fn (string $scope) => new Scope($scope), $dto->scopes));
        $client->setRedirectUris(...array_map(fn (string $redirectUri) => $this->validateRedirectUri($redirectUri), $dto->redirectUris));
        if($image !== null) {
            $client->setImage($image);
        }

        $this->manager->save($client);

        return $client;
    }

    private function createClientUser(OAuth2ClientDto $dto): User
    {
        if ($this->userRepository->findOneByUsername($dto->username)) {
            throw new BadRequestHttpException('That username/email is taken!');
        }
        if ($this->userRepository->findOneBy(['email' => $dto->contactEmail])) {
            throw new BadRequestHttpException('That username/email is taken!');
        }

        $userDto = new UserDto();
        $userDto->username = $dto->username;
        $userDto->email = $dto->contactEmail;
        // Only way to authenticate as this user will be to use client_credentials, unless they guess the very random password
        $userDto->plainPassword = hash('sha512', random_bytes(512));
        // This user is a bot user.
        $userDto->isBot = true;

        // Rate limiting is handled by the apiClientLimiter
        return $this->userManager->create($userDto, false, false);
    }

    private function validateRedirectUri(string $uri): RedirectUri {
        $host = parse_url($uri, PHP_URL_HOST);
        if($host === null || $host === $this->settingsManager->isLocalUrl($uri)) {
            throw new BadRequestHttpException('Invalid redirect URI: ' . $uri);
        }

        return new RedirectUri($uri);
    }

    private function getPasswordHasher(): PasswordHasherInterface
    {
        if($this->passwordHasher === null) {
            $this->passwordHasher = new NativePasswordHasher();
        }
        return $this->passwordHasher;
    }
}
