<?php

declare(strict_types=1);

namespace App\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class SimpleLogin extends AbstractProvider
{
    use BearerAuthorizationTrait;

    protected $baseUrl = 'https://app.simplelogin.io/';

    public function __construct(array $options = [], array $collaborators = [])
    {
        parent::__construct($options, $collaborators);
    }

    protected function getBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/').'/';
    }

    protected function getAuthorizationHeaders($token = null): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    public function getBaseAuthorizationUrl(): string
    {
        return $this->getBaseUrl().'oauth2/authorize';
    }

    public function getBaseAccessTokenUrl(array $params): string
    {
        return $this->getBaseUrl().'oauth2/token';
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        return $this->getBaseUrl().'oauth2/userinfo';
    }

    protected function getDefaultScopes(): array
    {
        return ['openid', 'profile', 'email'];
    }

    protected function checkResponse(ResponseInterface $response, $data): void
    {
        if (!empty($data['error'])) {
            $error = htmlentities($data['error'], ENT_QUOTES, 'UTF-8');
            $message = htmlentities($data['error_description'], ENT_QUOTES, 'UTF-8');
            throw new IdentityProviderException($message, $response->getStatusCode(), $response);
        }
    }

    protected function createResourceOwner(array $response, AccessToken $token): SimpleLoginResourceOwner
    {
        return new SimpleLoginResourceOwner($response);
    }

    protected function getScopeSeparator(): string
    {
        return ' ';
    }
}
