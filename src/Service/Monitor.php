<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\MonitoringCurlRequest;
use App\Entity\MonitoringExecutionContext;
use App\Entity\MonitoringQuery;
use App\Entity\MonitoringQueryString;
use App\Entity\MonitoringTwigRender;
use App\Entity\Traits\MonitoringPerformanceTrait;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class Monitor
{
    public ?MonitoringExecutionContext $currentContext = null;
    protected array $contexts = [];

    protected array $contextSegments = [];
    protected array $oldContextSegments = [];
    protected ?MonitoringQuery $currentQuery = null;
    protected ?MonitoringCurlRequest $currentCurlRequest = null;

    /**
     * @var array<array{level: int, render: MonitoringTwigRender}>
     */
    protected array $runningTwigTemplates = [];
    protected ?float $startSendingResponseTime = null;
    protected ?float $endSendingResponseTime = null;

    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly LoggerInterface $logger,
        private readonly bool $monitoringEnabled,
        private readonly bool $monitoringQueryParametersEnabled,
    ) {
    }

    public function shouldRecord(): bool
    {
        return $this->monitoringEnabled;
    }

    /**
     * @param string $executionType 'request'|'messenger'
     * @param string $userType      'anonymous'|'user'|'activity_pub'|'ajax'
     * @param string $path          the path or the message class
     * @param string $handler       the controller or the message handler
     */
    public function startNewExecutionContext(string $executionType, string $userType, string $path, string $handler): void
    {
        $context = new MonitoringExecutionContext();
        $context->executionType = $executionType;
        $context->path = $path;
        $context->userType = $userType;
        $context->handler = $handler;
        $context->setStartedAt();

        $this->contexts[] = $context;
        $this->currentContext = $context;
        $this->oldContextSegments = array_merge($this->oldContextSegments, $this->contextSegments);
        $this->contextSegments = [];
        $this->logger->debug('[Monitor] Starting a new execution context, type: {executionType}, user: {user}, path: {path}, handler: {handler}', [
            'executionType' => $executionType,
            'user' => $userType,
            'path' => $path,
            'handler' => $handler,
        ]);
    }

    public function endCurrentExecutionContext(?int $statusCode = null, ?string $exception = null, ?string $stacktrace = null): void
    {
        if (null === $this->currentContext) {
            $this->logger->error('[Monitor] Trying to end a context, but the current one is null');

            return;
        }

        $this->currentContext->setEndedAt();
        $this->currentContext->setDuration();
        if (null !== $statusCode) {
            $this->currentContext->statusCode = $statusCode;
        }
        if (null !== $exception) {
            $this->currentContext->exception = $exception;
        }
        if (null !== $stacktrace) {
            $this->currentContext->stacktrace = $stacktrace;
        }

        $this->logger->debug('[Monitor] Ending an new execution context, type: {executionType}, user: {user}, path: {path}, handler: {handler}, status code: {statusCode}, exception: {exception}, stacktrace: {stacktrace}', [
            'executionType' => $this->currentContext->executionType,
            'user' => $this->currentContext->userType,
            'path' => $this->currentContext->path,
            'handler' => $this->currentContext->handler,
            'statusCode' => $this->currentContext->statusCode,
            'exception' => $this->currentContext->exception,
            'stacktrace' => $this->currentContext->stacktrace,
        ]);

        $this->currentContext->queryDurationMilliseconds = $this->calculateDurationFromCollection(array_filter($this->contextSegments, fn ($item) => $item instanceof MonitoringQuery));
        $this->currentContext->twigRenderDurationMilliseconds = $this->calculateDurationFromCollection(array_filter($this->contextSegments, fn ($item) => $item instanceof MonitoringTwigRender && null === $item->parent));
        $this->currentContext->curlRequestDurationMilliseconds = $this->calculateDurationFromCollection(array_filter($this->contextSegments, fn ($item) => $item instanceof MonitoringCurlRequest));

        try {
            $this->entityManager->persist($this->currentContext);
            $queryStringRepo = $this->entityManager->getRepository(MonitoringQueryString::class);
            $queryStringsByHash = [];
            foreach ($this->contextSegments as $contextSegment) {
                if ($contextSegment instanceof MonitoringQuery) {
                    // we don't want to compute hashes during event listening, as even sha1 will be a bit time-consuming
                    $hash = hash('sha1', $contextSegment->queryString->query);
                    if (\array_key_exists($hash, $queryStringsByHash)) {
                        $contextSegment->queryString = $queryStringsByHash[$hash];
                    }
                    $queryString = $queryStringRepo->find($hash);
                    if (null !== $queryString) {
                        $queryStringsByHash[$hash] = $queryString;
                        $contextSegment->queryString = $queryString;
                    } else {
                        // not in cache and not in DB -> persist new entity
                        $queryStringsByHash[$hash] = $contextSegment->queryString;
                        $contextSegment->queryString->queryHash = $hash;
                        $this->entityManager->persist($contextSegment->queryString);
                    }
                }
                $this->entityManager->persist($contextSegment);
            }
            $this->entityManager->flush();
            $this->currentContext = null;
        } catch (\Throwable $exception) {
            $this->logger->critical('[Monitor] Error during context processing: {m}', [
                'm' => $exception->getMessage(),
                'exception' => $exception,
            ]);
        }
    }

    public function cancelCurrentExecutionContext(): void
    {
        $this->contexts = array_filter($this->contexts, fn (MonitoringExecutionContext $context) => $this->currentContext !== $context);
        $this->currentContext = null;
        $this->contextSegments = [];
    }

    public function startQuery(string $sql, ?array $parameters = null): void
    {
        if (null === $this->currentContext) {
            $this->logger->error('[Monitor] Trying to start a query, but the current context is null');

            return;
        }
        if (null !== $this->currentQuery) {
            $this->logger->error('[Monitor] Trying to start a query, but another one is still running');

            return;
        }
        $this->logger->debug('[Monitor] starting a query');
        $queryString = new MonitoringQueryString();
        $queryString->query = $sql;
        $this->currentQuery = new MonitoringQuery();
        $this->currentQuery->setStartedAt();
        $this->currentQuery->queryString = $queryString;
        if ($this->monitoringQueryParametersEnabled) {
            $this->currentQuery->parameters = $parameters;
        }
        $this->currentQuery->context = $this->currentContext;
    }

    public function endQuery(): void
    {
        if (null === $this->currentQuery) {
            $this->logger->error('[Monitor] Trying to end a query, but the current one is null');

            return;
        }
        $this->logger->debug('[Monitor] ending a query');
        $this->currentQuery->setEndedAt();
        $this->currentQuery->setDuration();
        if ($this->monitoringQueryParametersEnabled) {
            $this->currentQuery->cleanParameterArray();
        }
        $this->contextSegments[] = $this->currentQuery;
        $this->currentQuery = null;
    }

    public function startTwigRendering(string $templateName, string $type): void
    {
        if (\array_key_exists($templateName, $this->runningTwigTemplates)) {
            $this->logger->error('[Monitor] Trying to start a twig render which is already running ({name})', ['name' => $templateName]);

            return;
        }
        $this->logger->debug('[Monitor] Starting a twig render of {name}, {type} at level {level}', ['name' => $templateName, 'type' => $type, 'level' => \sizeof($this->runningTwigTemplates)]);

        $render = new MonitoringTwigRender();
        $render->templateName = $templateName;
        $render->context = $this->currentContext;
        $render->shortDescription = $templateName;
        $render->setStartedAt();

        $maxLevel = 0;
        $parent = null;
        foreach ($this->runningTwigTemplates as $obj) {
            if ($obj['level'] > $maxLevel) {
                $maxLevel = $obj['level'];
                $parent = $obj['render'];
            }
        }

        if (null !== $parent) {
            $render->parent = $parent;
        }

        $this->runningTwigTemplates[] = [
            'level' => $maxLevel + 1,
            'render' => $render,
        ];
    }

    public function endTwigRendering(string $templateName, ?int $memoryUsage, ?int $peakMemoryUsage, ?string $name, ?string $type, ?float $profilerDuration): void
    {
        if (0 === \sizeof($this->runningTwigTemplates)) {
            $this->logger->error('[Monitor] Trying to end a twig render but none have been started ({name})', ['name' => $templateName]);

            return;
        }
        $this->logger->debug('[Monitor] Ending a twig render of {name}', ['name' => $templateName]);

        $lastTemplate = array_pop($this->runningTwigTemplates);
        /** @var MonitoringTwigRender $render */
        $render = $lastTemplate['render'];

        if ($templateName !== $render->templateName) {
            $this->logger->warning('[Monitor] the popped twig render has a different template name than the one that should be ended: {name} !== {renderTemplateName}', ['name' => $templateName, 'renderTemplateName' => $render->templateName]);
        }

        $render->setEndedAt();
        $render->setDuration();
        $render->memoryUsage = $memoryUsage;
        $render->peakMemoryUsage = $peakMemoryUsage;
        $render->name = $name;
        $render->type = $type;
        $render->profilerDuration = $profilerDuration;

        $this->contextSegments[] = $render;
    }

    public function startCurlRequest(string $targetUrl, string $method): void
    {
        if (null === $this->currentContext) {
            $this->logger->error('[Monitor] Trying to start a curl request, but the current context is null');

            return;
        }
        if (null !== $this->currentCurlRequest) {
            $this->logger->warning('[Monitor] Trying to start a curl request, but another one is running');

            return;
        }
        $this->logger->debug('[Monitor] Starting a curl request of {method} - {url}', ['method' => $method, 'url' => $targetUrl]);

        $this->currentCurlRequest = new MonitoringCurlRequest();
        $this->currentCurlRequest->url = $targetUrl;
        $this->currentCurlRequest->method = $method;
        $this->currentCurlRequest->context = $this->currentContext;
        $this->currentCurlRequest->setStartedAt();
    }

    public function endCurlRequest(string $url, bool $wasSuccessful, ?\Throwable $exception): void
    {
        if (null === $this->currentContext) {
            $this->logger->error('[Monitor] Trying to end a curl request, but the current context is null');

            return;
        }
        if (null === $this->currentCurlRequest) {
            $this->logger->warning('[Monitor] Trying to end a curl request, but the current request is null');

            return;
        }
        if ($this->currentCurlRequest->url !== $url) {
            // should never occur, as php is single threaded
            $this->logger->warning('[Monitor] Trying to end a curl request, but the current request is using another URL: {u1} !== {u2}', ['u1' => $this->currentCurlRequest->url, 'u2' => $url]);

            return;
        }
        $this->logger->debug('[Monitor] Ending a curl request of {url}, was successful: {success}', ['url' => $url, 'success' => $wasSuccessful]);

        $this->currentCurlRequest->setEndedAt();
        $this->currentCurlRequest->setDuration();
        $this->currentCurlRequest->wasSuccessful = $wasSuccessful;
        if (null !== $exception) {
            $this->currentCurlRequest->exception = \get_class($exception).": {$exception->getMessage()}";
        }
        $this->contextSegments[] = $this->currentCurlRequest;
        $this->currentCurlRequest = null;
    }

    /**
     * @param iterable<MonitoringPerformanceTrait> $collection
     */
    protected function calculateDurationFromCollection(iterable $collection): float
    {
        $duration = 0;
        foreach ($collection as $item) {
            $duration += $item->getDuration();
        }

        return $duration;
    }

    public function startSendingResponse(): void
    {
        if (null === $this->currentContext || 'response' !== $this->currentContext->executionType) {
            $this->startSendingResponseTime = null;

            return;
        }
        $this->startSendingResponseTime = microtime(true);
    }

    public function endSendingResponse(): void
    {
        if (null === $this->currentContext || 'response' !== $this->currentContext->executionType || null === $this->startSendingResponseTime) {
            $this->endSendingResponseTime = null;

            return;
        }
        $this->endSendingResponseTime = microtime(true);
        $this->currentContext->responseSendingDurationMilliseconds = ($this->endSendingResponseTime - $this->startSendingResponseTime) * 1000;
    }
}
