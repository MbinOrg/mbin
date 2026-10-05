<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\RouterContextHostSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\UrlMatcher;
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

    private function makeKernel(string $kbinDomain, ?LoggerInterface $logger = null): HttpKernel
    {
        // Start with an already canonical context: validation must inspect the
        // incoming request, not trust the context left by a previous request.
        $context = new RequestContext('', 'GET', 'mbin.example', 'https');
        $routes = new RouteCollection();
        $routes->add('request', new Route('/reset-password', [
            '_controller' => fn (): Response => new Response($this->generateAbsoluteUrl($context)),
        ]));
        $requestStack = new RequestStack();
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouterListener(new UrlMatcher($routes, $context), $requestStack, $context));
        $dispatcher->addSubscriber($this->makeSubscriber($context, $kbinDomain, $logger));
        $dispatcher->addListener(KernelEvents::EXCEPTION, static function (ExceptionEvent $event): void {
            $exception = $event->getThrowable();
            if ($exception instanceof HttpExceptionInterface) {
                $event->setResponse(new Response($exception->getMessage(), $exception->getStatusCode()));
            }
        });

        return new HttpKernel($dispatcher, new ControllerResolver(), $requestStack, new ArgumentResolver());
    }

    #[DataProvider('unexpectedRequestUrls')]
    public function testUnexpectedRequestHostIsRejectedBeforeRouting(string $url): void
    {
        $response = $this->makeKernel('mbin.example')->handle(Request::create($url));

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public static function unexpectedRequestUrls(): iterable
    {
        yield 'existing route' => ['https://attacker.evil.example/reset-password'];
        yield 'unknown route' => ['https://attacker.evil.example/does-not-exist'];
        yield 'hostname suffix' => ['https://mbin.example.attacker.evil.example/reset-password'];
        yield 'unconfigured subdomain' => ['https://www.mbin.example/reset-password'];
    }

    public function testRejectedRequestHostIsLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('Rejected request with an unexpected hostname', [
                'request_host' => 'attacker.evil.example',
                'configured_host' => 'mbin.example',
            ]);

        $response = $this->makeKernel('mbin.example', $logger)->handle(
            Request::create('https://attacker.evil.example/reset-password'),
        );

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[DataProvider('approvedRequestUrls')]
    public function testApprovedRequestHostIsAccepted(string $kbinDomain, string $url, string $expectedUrl): void
    {
        $response = $this->makeKernel($kbinDomain)->handle(Request::create($url));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame($expectedUrl, $response->getContent());
    }

    public static function approvedRequestUrls(): iterable
    {
        yield 'HTTPS' => ['mbin.example', 'https://mbin.example/reset-password', 'https://mbin.example/reset/token'];
        yield 'HTTP and different port' => ['mbin.example', 'http://mbin.example:8080/reset-password', 'https://mbin.example/reset/token'];
        yield 'configured port' => ['localhost:8000', 'http://localhost:9000/reset-password', 'https://localhost:8000/reset/token'];
        yield 'configured HTTP scheme' => ['http://mbin.example:8080', 'https://mbin.example:9443/reset-password', 'http://mbin.example:8080/reset/token'];
        yield 'uppercase configured host' => ['MBIN.EXAMPLE', 'https://mbin.example/reset-password', 'https://mbin.example/reset/token'];
        yield 'uppercase request host' => ['mbin.example', 'https://MBIN.EXAMPLE/reset-password', 'https://mbin.example/reset/token'];
        yield 'IPv6 and different port' => ['[::1]:8000', 'http://[::1]:9000/reset-password', 'https://[::1]:8000/reset/token'];
    }

    public function testInternalSubRequestWithDifferentHostIsCanonicalized(): void
    {
        $response = $this->makeKernel('mbin.example')->handle(
            Request::create('http://fragment.evil.example/reset-password'),
            HttpKernelInterface::SUB_REQUEST,
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('https://mbin.example/reset/token', $response->getContent());
    }

    #[DataProvider('canonicalProxyRequests')]
    public function testReverseProxyUsesPublicSchemeAndPort(string $kbinDomain, string $scheme, int $port, string $expectedUrl): void
    {
        $trustedProxies = Request::getTrustedProxies();
        $trustedHeaders = Request::getTrustedHeaderSet();
        Request::setTrustedProxies(['10.0.0.1'], Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT);

        try {
            $request = Request::create('http://mbin.example:8080/reset-password', server: [
                'REMOTE_ADDR' => '10.0.0.1',
                'HTTP_X_FORWARDED_PROTO' => $scheme,
                'HTTP_X_FORWARDED_PORT' => (string) $port,
                'HTTP_X_FORWARDED_HOST' => 'attacker.evil.example',
            ]);
            self::assertSame('mbin.example', $request->getHost());
            self::assertSame($scheme, $request->getScheme());
            self::assertSame($port, $request->getPort());
            $logger = $this->createMock(LoggerInterface::class);
            $logger->expects(self::never())->method('warning');

            $response = $this->makeKernel($kbinDomain, $logger)->handle($request);

            self::assertSame(Response::HTTP_OK, $response->getStatusCode());
            self::assertSame($expectedUrl, $response->getContent());
        } finally {
            Request::setTrustedProxies($trustedProxies, $trustedHeaders);
        }
    }

    public static function canonicalProxyRequests(): iterable
    {
        yield 'public HTTPS over HTTP backend' => ['mbin.example', 'https', 443, 'https://mbin.example/reset/token'];
        yield 'custom public HTTPS port' => ['mbin.example:8443', 'https', 8443, 'https://mbin.example:8443/reset/token'];
        yield 'custom public HTTP port' => ['http://mbin.example:8080', 'http', 8080, 'http://mbin.example:8080/reset/token'];
    }

    public function testDivergingPublicProxyPortIsLoggedAndCanonicalized(): void
    {
        $trustedProxies = Request::getTrustedProxies();
        $trustedHeaders = Request::getTrustedHeaderSet();
        Request::setTrustedProxies(['10.0.0.1'], Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT);

        try {
            $request = Request::create('http://mbin.example:8080/reset-password', server: [
                'REMOTE_ADDR' => '10.0.0.1',
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_X_FORWARDED_PORT' => '9443',
            ]);
            self::assertSame('https', $request->getScheme());
            self::assertSame(9443, $request->getPort());
            $logger = $this->createMock(LoggerInterface::class);
            $logger->expects(self::once())->method('warning')->with(
                'Router request context diverged from the configured domain',
                self::callback(static fn (array $details): bool => 'https' === $details['context_scheme']
                    && 9443 === $details['context_https_port']
                    && 443 === $details['configured_https_port']),
            );

            $response = $this->makeKernel('mbin.example', $logger)->handle($request);

            self::assertSame(Response::HTTP_OK, $response->getStatusCode());
            self::assertSame('https://mbin.example/reset/token', $response->getContent());
        } finally {
            Request::setTrustedProxies($trustedProxies, $trustedHeaders);
        }
    }

    public function testForwardedHeadersFromUntrustedProxyAreIgnored(): void
    {
        $trustedProxies = Request::getTrustedProxies();
        $trustedHeaders = Request::getTrustedHeaderSet();
        Request::setTrustedProxies(['10.0.0.1'], Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT);

        try {
            $request = Request::create('https://mbin.example/reset-password', server: [
                'REMOTE_ADDR' => '198.51.100.10',
                'HTTP_X_FORWARDED_PROTO' => 'http',
                'HTTP_X_FORWARDED_PORT' => '31337',
                'HTTP_X_FORWARDED_HOST' => 'attacker.evil.example',
            ]);
            self::assertSame('mbin.example', $request->getHost());
            self::assertSame('https', $request->getScheme());
            self::assertSame(443, $request->getPort());
            $logger = $this->createMock(LoggerInterface::class);
            $logger->expects(self::never())->method('warning');

            $response = $this->makeKernel('mbin.example', $logger)->handle($request);

            self::assertSame(Response::HTTP_OK, $response->getStatusCode());
            self::assertSame('https://mbin.example/reset/token', $response->getContent());
        } finally {
            Request::setTrustedProxies($trustedProxies, $trustedHeaders);
        }
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

    public function testContextIsRestoredDuringAndAfterSubRequest(): void
    {
        self::assertSame(
            [
                KernelEvents::REQUEST => [
                    ['validateRequestHost', 64],
                    ['onKernelRequest', 16],
                ],
                KernelEvents::FINISH_REQUEST => ['onKernelFinishRequest', -16],
            ],
            RouterContextHostSubscriber::getSubscribedEvents(),
        );

        $context = new RequestContext();
        $kernel = $this->createStub(HttpKernelInterface::class);
        $mainRequest = Request::create('http://attacker.evil.example/reset-password');
        $subRequest = Request::create('http://fragment.evil.example/_fragment');
        $subscriber = $this->makeSubscriber($context, 'mbin.victim.example');

        // Simulate RouterListener replacing the context for the sub-request.
        $context->fromRequest($subRequest);
        $subscriber->onKernelRequest(new RequestEvent($kernel, $subRequest, HttpKernelInterface::SUB_REQUEST));

        self::assertSame('mbin.victim.example', $context->getHost());

        // RouterListener restores the parent request context on kernel.finish_request.
        $context->fromRequest($mainRequest);
        $subscriber->onKernelFinishRequest(
            new FinishRequestEvent($kernel, $subRequest, HttpKernelInterface::SUB_REQUEST),
        );

        self::assertSame('mbin.victim.example', $context->getHost());
    }
}
