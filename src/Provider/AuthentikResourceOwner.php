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

    public function getId()
    {
        return $this->getResponseValue('sub');
    }

    public function getEmail()
    {
        return $this->getResponseValue('email');
    }

    public function getFamilyName()
    {
        return $this->getResponseValue('family_name');
    }

    public function getGivenName()
    {
        return $this->getResponseValue('given_name');
    }

    public function getPreferredUsername()
    {
        return $this->getResponseValue('preferred_username');
    }

    public function getPictureUrl()
    {
        return $this->getResponseValue('picture');
    }

    public function toArray()
    {
        return $this->response;
    }

    protected function getResponseValue($key)
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
