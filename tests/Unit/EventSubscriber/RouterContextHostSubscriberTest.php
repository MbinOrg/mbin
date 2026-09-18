<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\RouterContextHostSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

class RouterContextHostSubscriberTest extends TestCase
{
    private function makeEvent(string $requestHost): RequestEvent
    {
        $request = Request::create('http://'.$requestHost.'/reset-password');
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function makeSubscriber(RequestContext $context, string $kbinDomain): RouterContextHostSubscriber
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('getContext')->willReturn($context);

        return new RouterContextHostSubscriber($router, $kbinDomain);
    }

    public function testPoisonedHostIsOverriddenByConfiguredDomain(): void
    {
        // Simulate the state after RouterListener has copied the attacker Host
        // into the router context.
        $context = new RequestContext();
        $context->setHost('attacker.evil.example');

        $subscriber = $this->makeSubscriber($context, 'mbin.victim.example');
        $subscriber->onKernelRequest($this->makeEvent('attacker.evil.example'));

        self::assertSame(
            'mbin.victim.example',
            $context->getHost(),
            'Router context host must be pinned to KBIN_DOMAIN, not the request Host.'
        );
    }

    public function testLegitimateHostRemainsConfiguredDomain(): void
    {
        $context = new RequestContext();
        $context->setHost('mbin.victim.example');

        $subscriber = $this->makeSubscriber($context, 'mbin.victim.example');
        $subscriber->onKernelRequest($this->makeEvent('mbin.victim.example'));

        self::assertSame('mbin.victim.example', $context->getHost());
    }

    public function testDomainWithSchemePinsHostAndScheme(): void
    {
        $context = new RequestContext();
        $context->setHost('attacker.evil.example');
        $context->setScheme('http');

        $subscriber = $this->makeSubscriber($context, 'https://mbin.victim.example');
        $subscriber->onKernelRequest($this->makeEvent('attacker.evil.example'));

        self::assertSame('mbin.victim.example', $context->getHost());
        self::assertSame('https', $context->getScheme());
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

        $router = $this->createMock(RouterInterface::class);
        $router->method('getContext')->willReturn($context);
        $subscriber = new RouterContextHostSubscriber($router, 'mbin.victim.example');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://attacker.evil.example/reset-password');
        $subRequest = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $subscriber->onKernelRequest($subRequest);

        // Sub-requests are ignored; only the main request pins the host.
        self::assertSame('attacker.evil.example', $context->getHost());
    }
}
