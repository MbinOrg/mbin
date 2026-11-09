<?php

namespace App\Tests\Service;

use stdClass;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\HttpClientTrait;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Same as Symfony\Component\HttpClient\MockHttpClient but the $responseFactory is shared between all derived instances
 */
class SharedMockHttpClient implements HttpClientInterface, ResetInterface
{
    use HttpClientTrait;

    private StdClass $responseFactoryHolder;
    private int $requestsCount = 0;
    private array $defaultOptions = [];

    public function __construct(?string $baseUri = 'https://example.com')
    {
        $this->responseFactoryHolder = (object) ['value' => null];
        $this->defaultOptions['base_uri'] = $baseUri;
    }

    /**
     * @param iterable|callable|callable[]|ResponseInterface|ResponseInterface[]|null $responseFactory
     */
    public function setResponseFactory($responseFactory): void
    {
        if ($responseFactory instanceof ResponseInterface) {
            $responseFactory = [$responseFactory];
        }

        if (!$responseFactory instanceof \Iterator && null !== $responseFactory && !\is_callable($responseFactory)) {
            $responseFactory = (static function () use ($responseFactory) {
                yield from $responseFactory;
            })();
        }

        $this->responseFactoryHolder->value = !\is_callable($responseFactory) ? $responseFactory : $responseFactory(...);
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        [$url, $options] = $this->prepareRequest($method, $url, $options, $this->defaultOptions, true);
        $url = implode('', $url);

        $factory = &$this->responseFactoryHolder->value;
        if (null === $factory) {
            $response = new MockResponse();
        } elseif (\is_callable($factory)) {
            $response = ($factory)($method, $url, $options);
        } elseif (!$factory->valid()) {
            throw new TransportException($this->requestsCount ? 'No more response left in the response factory iterator passed to MockHttpClient: the number of requests exceeds the number of responses.' : 'The response factory iterator passed to MockHttpClient is empty.');
        } else {
            $responseFactory = $factory->current();
            $response = \is_callable($responseFactory) ? $responseFactory($method, $url, $options) : $responseFactory;
            $factory->next();
        }
        ++$this->requestsCount;

        if (!$response instanceof ResponseInterface) {
            throw new TransportException(\sprintf('The response factory passed to MockHttpClient must return/yield an instance of ResponseInterface, "%s" given.', get_debug_type($response)));
        }

        return MockResponse::fromRequest($method, $url, $options, $response);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        if ($responses instanceof ResponseInterface) {
            $responses = [$responses];
        }

        return new ResponseStream(MockResponse::stream($responses, $timeout));
    }

    public function getRequestsCount(): int
    {
        return $this->requestsCount;
    }

    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->defaultOptions = self::mergeDefaultOptions($options, $this->defaultOptions, true);

        return $clone;
    }

    public function reset(): void
    {
        $this->requestsCount = 0;
    }
}
