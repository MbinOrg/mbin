<?php
declare(strict_types=1);

namespace App\Utils;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Psr18Client;

readonly class SizeLimitingHttpClient implements ClientInterface
{
    private Psr18Client $inner;

    public function __construct(
        Psr18Client $inner,
        private int $maxSize,
    ) {
        $this->inner = $inner->withOptions(['on_progress' => $this->sizeLimitingProgressListener()]);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $response = $this->inner->sendRequest($request);
        if ((int) $response->getHeaderLine('Content-Length') > $this->maxSize) {
            throw new TransportException("Http response larger than $this->maxSize bytes");
        }
        return $response;
    }

    private function sizeLimitingProgressListener(): callable
    {
        return function (int $dlNow, int $dlSize) {
            if ($dlSize > $this->maxSize || $dlNow > $this->maxSize) {
                throw new TransportException("Http response larger than $this->maxSize bytes");
            }
        };
    }
}
