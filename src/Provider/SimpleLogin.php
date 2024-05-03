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

    protected function getBaseUrl()
    {
        return rtrim($this->baseUrl, '/').'/';
    }

    protected function getAuthorizationHeaders($token = null)
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    public function getBaseAuthorizationUrl()
    {
        return $this->getBaseUrl().'oauth/v2/authorize';
    }

    public function getBaseAccessTokenUrl(array $params)
    {
        return $this->getBaseUrl().'oauth/v2/token';
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        return $this->getBaseUrl().'oidc/v1/userinfo';
    }

    protected function getDefaultScopes()
    {
        return ['openid', 'profile', 'email'];
    }

    protected function checkResponse(ResponseInterface $response, $data)
    {
        if (!empty($data['error'])) {
            $error = htmlentities($data['error'], ENT_QUOTES, 'UTF-8');
            $message = htmlentities($data['error_description'], ENT_QUOTES, 'UTF-8');
            throw new IdentityProviderException($message, $response->getStatusCode(), $response);
        }
    }

    protected function createResourceOwner(array $response, AccessToken $token)
    {
        return new SimpleLoginResourceOwner($response);
    }

    protected function getScopeSeparator()
    {
        return ' ';
    }
}
