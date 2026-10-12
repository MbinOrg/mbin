<?php

namespace App\Service;

use Symfony\Component\Cache\ResettableInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class UrlAccessChecker implements ResettableInterface
{
    private NoPrivateNetworkHttpClient $privateHostCheck;

    public function __construct() {
        $this->privateHostCheck = new NoPrivateNetworkHttpClient(new MockHttpClient());
    }

    public function reset(): void
    {
        $this->privateHostCheck->reset();
    }

    public function checkNoPrivateNetwork(string $url): bool {
        try {
            $this->privateHostCheck->request('GET', $url);
            return true;
        } catch (TransportExceptionInterface) {
            return false;
        }
    }
}
