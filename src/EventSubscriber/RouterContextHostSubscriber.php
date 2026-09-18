<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Pins the router request context host to the instance's configured domain.
 *
 * Symfony's RouterListener overwrites the RequestContext host with the value
 * of the incoming request Host header on every request. Absolute URLs built
 * with the Twig url() function or the UrlGenerator (for example the emailed
 * password-reset link) therefore inherit whatever Host the client sent. With
 * an empty framework.trusted_hosts (the default), that host is attacker
 * controllable, allowing password-reset link poisoning.
 *
 * Running after RouterListener and re-setting the context host to KBIN_DOMAIN
 * makes every generated absolute URL use the configured canonical host,
 * independent of the request Host header and of any reverse-proxy setup.
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

        $context = $this->router->getContext();
        $host = $this->kbinDomain;
        $scheme = null;

        if (str_contains($this->kbinDomain, '://')) {
            $parts = parse_url($this->kbinDomain);
            $host = $parts['host'] ?? $this->kbinDomain;
            $scheme = $parts['scheme'] ?? null;
        }

        $context->setHost($host);

        if (null !== $scheme) {
            $context->setScheme($scheme);
        }
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
