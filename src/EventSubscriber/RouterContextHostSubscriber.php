<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Restores the configured canonical URL after the router processes a request.
 */
readonly class RouterContextHostSubscriber implements EventSubscriberInterface
{
    private ?string $host;
    private string $scheme;
    private ?int $port;

    public function __construct(
        private RouterInterface $router,
        private LoggerInterface $logger,
        string $kbinDomain,
    ) {
        $canonicalUrl = str_contains($kbinDomain, '://')
            ? $kbinDomain
            : 'https://'.$kbinDomain;
        $parts = parse_url($canonicalUrl);

        $this->host = false !== $parts ? $parts['host'] ?? null : null;
        $this->scheme = false !== $parts ? $parts['scheme'] ?? 'https' : 'https';
        $this->port = false !== $parts ? $parts['port'] ?? null : null;
    }

    public static function getSubscribedEvents(): array
    {
        // Priority below RouterListener::onKernelRequest (priority 32) so this
        // runs after the context host has been (re)built from the request.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 16],
            // RouterListener restores the parent request context at priority 0.
            KernelEvents::FINISH_REQUEST => ['onKernelFinishRequest', -16],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $this->restoreCanonicalContext();
    }

    public function onKernelFinishRequest(FinishRequestEvent $event): void
    {
        $this->restoreCanonicalContext();
    }

    private function restoreCanonicalContext(): void
    {
        if (null === $this->host) {
            return;
        }

        $context = $this->router->getContext();
        $httpPort = 'http' === $this->scheme && null !== $this->port ? $this->port : 80;
        $httpsPort = 'https' === $this->scheme && null !== $this->port ? $this->port : 443;

        if ($this->host !== $context->getHost()
            || $this->scheme !== $context->getScheme()
            || $httpPort !== $context->getHttpPort()
            || $httpsPort !== $context->getHttpsPort()) {
            $this->logger->warning('Router request context diverged from the configured domain', [
                'context_host' => $context->getHost(),
                'context_scheme' => $context->getScheme(),
                'context_http_port' => $context->getHttpPort(),
                'context_https_port' => $context->getHttpsPort(),
                'configured_host' => $this->host,
                'configured_scheme' => $this->scheme,
                'configured_http_port' => $httpPort,
                'configured_https_port' => $httpsPort,
            ]);
        }

        $context->setHost($this->host);
        $context->setScheme($this->scheme);
        $context->setHttpPort($httpPort);
        $context->setHttpsPort($httpsPort);
    }
}
