<?php
declare(strict_types=1);

namespace App\Factory;

use Embed\Http\Crawler;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Creates HttpClients which block requests to non-WWW (e.g. localhost and LAN) destinations.
 */
class WwwHttpClientFactory implements ResetInterface
{
    private readonly HttpClientInterface $defaultClient;
    private array $existingClients;

    public function __construct(
        private readonly HttpClientInterface $httpClientBase,
    ) {
        $this->existingClients = [];
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

    /**
     * Link previews only need the page head, but the response body is read entirely into memory.
     * Without a cap, a link to a large media file (e.g. a 170 MB video) exhausts the PHP memory limit
     * and kills the messenger worker handling the message.
     */
    private const int EMBED_MAX_BYTES = 10 * 1024 * 1024;

    public function getEmbedCrawler(?HttpClientInterface $base = null, ?array $clientOptions = null): Crawler
    {
        $maxBytes = self::EMBED_MAX_BYTES;
        $psr18 = new Psr18Client($this->getClient($base, $clientOptions)->withOptions([
            // Covers responses that do not announce their size (e.g. chunked transfer encoding).
            'on_progress' => static function (int $dlNow, int $dlSize) use ($maxBytes): void {
                if ($dlSize > $maxBytes || $dlNow > $maxBytes) {
                    throw new \RuntimeException("Embed response larger than $maxBytes bytes");
                }
            },
        ]));

        // Reject on Content-Length before the body is read: reading the stream preallocates a buffer of that size.
        $client = new class($psr18, $maxBytes) implements ClientInterface {
            public function __construct(
                private readonly ClientInterface $inner,
                private readonly int $maxBytes,
            ) {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $response = $this->inner->sendRequest($request);
                if ((int) $response->getHeaderLine('Content-Length') > $this->maxBytes) {
                    throw new \RuntimeException("Embed response larger than {$this->maxBytes} bytes");
                }

                return $response;
            }
        };

        return new Crawler($client, $psr18, $psr18);
    }

    private function buildConfiguredClient(HttpClientInterface $client): HttpClientInterface
    {
        return $client->withOptions([
            'max_redirects' => 10,
            'max_duration' => 10,
            'timeout' => 10,
        ]);
    }

    public function reset(): void
    {
        $stillExistingClients = [];
        foreach ($this->existingClients as $clientRef) {
            /** @var \WeakReference<NoPrivateNetworkHttpClient> $clientRef */
            $client = $clientRef->get();
            if (null !== $client) {
                $client->reset();
                $stillExistingClients[] = $clientRef;
            }
        }

        $this->existingClients = $stillExistingClients;
    }

    private function buildFilteredClient(HttpClientInterface $client): HttpClientInterface
    {
        $ret = new NoPrivateNetworkHttpClient($client);
        $this->existingClients[] = \WeakReference::create($ret);
        return $ret;
    }
}
