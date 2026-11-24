<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Rejects unexpected request hosts and restores the configured canonical URL.
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

        $this->host = false !== $parts && isset($parts['host']) ? strtolower($parts['host']) : null;
        $this->scheme = false !== $parts ? $parts['scheme'] ?? 'https' : 'https';
        $this->port = false !== $parts ? $parts['port'] ?? null : null;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                // Reject unexpected main-request hosts before routing (priority 32).
                ['validateRequestHost', 64],
                // Restore canonical context after RouterListener rebuilds it.
                ['onKernelRequest', 16],
            ],
            // RouterListener restores the parent request context at priority 0.
            KernelEvents::FINISH_REQUEST => ['onKernelFinishRequest', -16],
        ];
    }

    public function validateRequestHost(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || null === $this->host) {
            return;
        }

        $requestHost = $event->getRequest()->getHost();
        if ($requestHost === $this->host) {
            return;
        }

        $this->logger->warning('Rejected request with an unexpected hostname', [
            'request_host' => $requestHost,
            'configured_host' => $this->host,
        ]);

        throw new BadRequestHttpException('The request hostname does not match the configured instance domain.');
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
