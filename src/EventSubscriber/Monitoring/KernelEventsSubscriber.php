<?php

declare(strict_types=1);

namespace App\EventSubscriber\Monitoring;

use App\Service\Monitor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Heavily inspired by https://github.com/inspector-apm/inspector-symfony/blob/master/src/Listeners/KernelEventsSubscriber.php.
 */
readonly class KernelEventsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Monitor $monitor,
        private Security $security,
        private RouterInterface $router,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest'],
            KernelEvents::CONTROLLER => ['onKernelController'],
            KernelEvents::EXCEPTION => ['onKernelException'],
            KernelEvents::RESPONSE => ['onKernelResponse'],
            KernelEvents::TERMINATE => ['onKernelResponseSent'],
        ];
    }

    public const array ROUTES_TO_IGNORE = [
        'ajax_fetch_user_notifications_count',
        'liip_imagine_filter',
        'custom_style',
        'admin_monitoring',
        'admin_monitoring_single_context',
        '_wdt',
        '_wdt_stylesheet',
    ];

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->monitor->shouldRecord()) {
            return;
        }
        $request = $event->getRequest();
        $acceptHeaders = $request->headers->all('Accept');
        if (0 < \sizeof($acceptHeaders) && (\in_array('application/activity+json', $acceptHeaders) || \in_array('application/ld+json', $acceptHeaders))) {
            $user = 'activity_pub';
        } elseif ($request->isXmlHttpRequest()) {
            $user = 'ajax';
        } elseif ($this->security->getUser()) {
            $user = 'user';
        } else {
            $user = 'anonymous';
        }

        try {
            $routeInfo = $this->router->matchRequest($request);
            $routeName = $routeInfo['_route'];
            if (\in_array($routeName, self::ROUTES_TO_IGNORE)) {
                return;
            }

            if (str_starts_with($routeName, 'ap_')) {
                $user = 'activity_pub';
            }
        } catch (\Exception) {
        }

        $this->monitor->startNewExecutionContext('request', $user, $routeName ?? $request->getRequestUri(), '');
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$this->monitor->shouldRecord() || null === $this->monitor->currentContext) {
            return;
        }
        $controller = $event->getController();
        if (\is_array($controller)) {
            $this->monitor->currentContext->handler = \get_class($controller[0]).'->'.$controller[1];
        } elseif (\is_object($controller)) {
            $this->monitor->currentContext->handler = \get_class($controller).'->__invoke';
        } elseif (\is_string($controller)) {
            $this->monitor->currentContext->handler = $controller;
        }
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$this->monitor->shouldRecord()) {
            return;
        }
        if (null !== $this->monitor->currentContext) {
            $this->monitor->currentContext->exception = \get_class($event->getThrowable());
            $this->monitor->currentContext->stacktrace = $event->getThrowable()->getTraceAsString();
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->monitor->shouldRecord() || null === $this->monitor->currentContext) {
            return;
        }
        $this->monitor->startSendingResponse();
    }

    public function onKernelResponseSent(TerminateEvent $event): void
    {
        if (!$this->monitor->shouldRecord() || null === $this->monitor->currentContext) {
            return;
        }
        $this->monitor->endSendingResponse();
        $response = $event->getResponse();
        $this->monitor->endCurrentExecutionContext($response->getStatusCode());
    }
}
