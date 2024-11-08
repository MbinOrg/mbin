<?php

declare(strict_types=1);

namespace App\Provider;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class SimpleLoginResourceOwner implements ResourceOwnerInterface
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

    public function getName(): mixed
    {
        return $this->getResponseValue('name');
    }

    public function getEmail(): mixed
    {
        return $this->getResponseValue('email');
    }

    public function getPictureUrl(): mixed
    {
        return $this->getResponseValue('avatar_url');
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
