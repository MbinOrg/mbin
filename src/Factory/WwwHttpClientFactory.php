<?php
declare(strict_types=1);

namespace App\Factory;

use Embed\Http\Crawler;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Creates HttpClients which block requests to non-WWW (e.g. localhost and LAN) destinations.
 */
readonly class WwwHttpClientFactory
{
    private HttpClientInterface $defaultClient;

    public function __construct(
        private HttpClientInterface $httpClientBase,
    ) {
        $this->defaultClient = $this->buildFilteredClient($this->buildConfiguredClient($this->httpClientBase));
    }

    public function getClient(?HttpClientInterface $base = null, ?array $clientOptions = null): HttpClientInterface
    {
        if (null === $base && null === $clientOptions) {
            return $this->defaultClient;
        } elseif (null === $clientOptions) {
            return $this->buildFilteredClient($this->buildConfiguredClient($base ?? $this->httpClientBase));
        } else {
            return $this->buildFilteredClient(($base ?? $this->httpClientBase)->withOptions($clientOptions));
        }
    }

    public function getPsr18Client(?HttpClientInterface $base = null, ?array $clientOptions = null): Psr18Client
    {
        $client = $this->getClient($base, $clientOptions);
        return new Psr18Client($client);
    }

    public function getEmbedCrawler(?HttpClientInterface $base = null, ?array $clientOptions = null): Crawler
    {
        $client = $this->getPsr18Client($base, $clientOptions);
        return new Crawler($client, $client, $client);
    }

    private function buildConfiguredClient(HttpClientInterface $client): HttpClientInterface
    {
        return $client->withOptions([
            'max_redirects' => 10,
            'max_duration' => 10,
            'timeout' => 10,
        ]);
    }

    private function buildFilteredClient(HttpClientInterface $client): HttpClientInterface
    {
        return new NoPrivateNetworkHttpClient($client);
    }
}
