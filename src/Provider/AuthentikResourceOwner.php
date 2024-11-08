<?php

declare(strict_types=1);

namespace App\Provider;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class AuthentikResourceOwner implements ResourceOwnerInterface
{
    protected $response;

    public function __construct(array $response)
    {
        $this->response = $response;
    }

    public function getId(): mixed
    {
        return $this->getResponseValue('sub');
    }

    public function getEmail(): mixed
    {
        return $this->getResponseValue('email');
    }

    public function getFamilyName(): mixed
    {
        return $this->getResponseValue('family_name');
    }

    public function getGivenName(): mixed
    {
        return $this->getResponseValue('given_name');
    }

    public function getPreferredUsername(): mixed
    {
        return $this->getResponseValue('preferred_username');
    }

    public function getPictureUrl(): mixed
    {
        return $this->getResponseValue('picture');
    }

    public function toArray(): array
    {
        return $this->response;
    }

    protected function getResponseValue($key): mixed
    {
        $keys = explode('.', $key);
        $value = $this->response;

        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return null;
            }
        }

        return $value;
    }
}
