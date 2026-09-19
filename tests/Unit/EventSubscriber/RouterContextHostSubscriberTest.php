<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\RouterContextHostSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

class RouterContextHostSubscriberTest extends TestCase
{
    private function makeEvent(string $requestHost): RequestEvent
    {
        $request = Request::create('http://'.$requestHost.'/reset-password');
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function makeSubscriber(
        RequestContext $context,
        string $kbinDomain,
        ?LoggerInterface $logger = null,
    ): RouterContextHostSubscriber {
        $router = $this->createStub(RouterInterface::class);
        $router->method('getContext')->willReturn($context);

        return new RouterContextHostSubscriber($router, $logger ?? $this->createStub(LoggerInterface::class), $kbinDomain);
    }

    private function generateAbsoluteUrl(RequestContext $context): string
    {
        $routes = new RouteCollection();
        $routes->add('reset', new Route('/reset/{token}'));

        return (new UrlGenerator($routes, $context))->generate(
            'reset',
            ['token' => 'token'],
            UrlGenerator::ABSOLUTE_URL,
        );
    }

    public function testRequestUrlIsReplacedByConfiguredDomain(): void
    {
        $context = new RequestContext();
        $context->fromRequest(Request::create('http://attacker.evil.example:8443/reset-password'));

        $subscriber = $this->makeSubscriber($context, 'mbin.example');
        $subscriber->onKernelRequest($this->makeEvent('attacker.evil.example'));

        self::assertSame('https://mbin.example/reset/token', $this->generateAbsoluteUrl($context));
    }

    public function testLegitimateHostRemainsConfiguredDomain(): void
    {
        $context = new RequestContext();
        $context->setHost('mbin.victim.example');
        $context->setScheme('https');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $subscriber = $this->makeSubscriber($context, 'mbin.victim.example', $logger);
        $subscriber->onKernelRequest($this->makeEvent('mbin.victim.example'));

        self::assertSame('mbin.victim.example', $context->getHost());
    }

    public function testDivergingContextIsLoggedBeforeItIsReplaced(): void
    {
        $context = new RequestContext();
        $context->fromRequest(Request::create('http://attacker.evil.example:8443/reset-password'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'Router request context diverged from the configured domain',
                self::callback(static fn (array $details): bool => 'attacker.evil.example' === $details['context_host']
                    && 'mbin.example' === $details['configured_host']),
            );

        $subscriber = $this->makeSubscriber($context, 'mbin.example', $logger);
        $subscriber->onKernelRequest($this->makeEvent('attacker.evil.example'));

        self::assertSame('mbin.example', $context->getHost());
    }

    public function testConfiguredDomainWithPortIsPreserved(): void
    {
        $context = new RequestContext();
        $context->fromRequest(Request::create('https://attacker.evil.example:9443/reset-password'));

        $subscriber = $this->makeSubscriber($context, 'localhost:8000');
        $subscriber->onKernelRequest($this->makeEvent('attacker.evil.example'));

        self::assertSame('https://localhost:8000/reset/token', $this->generateAbsoluteUrl($context));
    }

    public function testDomainWithSchemeAndPortIsPreserved(): void
    {
        $context = new RequestContext();
        $context->fromRequest(Request::create('https://attacker.evil.example:9443/reset-password'));

        $subscriber = $this->makeSubscriber($context, 'http://mbin.example:8080');
        $subscriber->onKernelRequest($this->makeEvent('attacker.evil.example'));

        self::assertSame('http://mbin.example:8080/reset/token', $this->generateAbsoluteUrl($context));
    }

    public function testEmptyDomainLeavesContextUntouched(): void
    {
        $context = new RequestContext();
        $context->setHost('attacker.evil.example');

        $subscriber = $this->makeSubscriber($context, '');
        $subscriber->onKernelRequest($this->makeEvent('attacker.evil.example'));

        // With no configured domain the subscriber is a no-op (nothing to pin to).
        self::assertSame('attacker.evil.example', $context->getHost());
    }

    public function testSubProblemRequestIsIgnored(): void
    {
        $context = new RequestContext();
        $context->setHost('attacker.evil.example');

        $router = $this->createStub(RouterInterface::class);
        $router->method('getContext')->willReturn($context);
        $subscriber = new RouterContextHostSubscriber(
            $router,
            $this->createStub(LoggerInterface::class),
            'mbin.victim.example',
        );

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('http://attacker.evil.example/reset-password');
        $subRequest = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $subscriber->onKernelRequest($subRequest);

        // Sub-requests are ignored; only the main request pins the host.
        self::assertSame('attacker.evil.example', $context->getHost());
    }
}
