<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Restores the configured canonical URL after the router processes a request.
 */
class RouterContextHostSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly string $kbinDomain,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ('' === $this->kbinDomain) {
            return;
        }

        $canonicalUrl = str_contains($this->kbinDomain, '://')
            ? $this->kbinDomain
            : 'https://'.$this->kbinDomain;
        $parts = parse_url($canonicalUrl);

        if (false === $parts || !isset($parts['host'])) {
            return;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $port = $parts['port'] ?? null;
        $context = $this->router->getContext();

        $context->setHost($parts['host']);
        $context->setScheme($scheme);
        $context->setHttpPort('http' === $scheme && null !== $port ? $port : 80);
        $context->setHttpsPort('https' === $scheme && null !== $port ? $port : 443);
    }

    public static function getSubscribedEvents(): array
    {
        // Priority below RouterListener::onKernelRequest (priority 32) so this
        // runs after the context host has been (re)built from the request.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 16],
        ];
    }
}
