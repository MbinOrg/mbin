<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\MonitoringExecutionContext;
use App\Entity\MonitoringQuery;
use App\Tests\WebTestCase;
use PHPUnit\Framework\Attributes\Depends;

class MonitoringParameterEncodingTest extends WebTestCase
{
    public function testThrowOnParameterEncoding(): void
    {
        $prepared = $this->prepareContextAndQuery();
        $query = $prepared['query'];

        $exception = null;
        try {
            $this->entityManager->persist($prepared['context']);
            $this->entityManager->persist($query);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            // this will throw an exception because the query parameters contain invalid characters
            $exception = $e;
        }

        self::assertNotNull($exception);
    }

    #[Depends('testThrowOnParameterEncoding')]
    public function testNotThrowOnEscape(): void
    {
        $prepared = $this->prepareContextAndQuery();
        $query = $prepared['query'];
        $query->cleanParameterArray();
        $exception = null;
        try {
            $this->entityManager->persist($prepared['context']);
            $this->entityManager->persist($query);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $exception = $e;
        }
        self::assertNull($exception);
    }

    private function prepareContextAndQuery(): array
    {
        $context = new MonitoringExecutionContext();
        $context->executionType = 'test';
        $context->path = 'test';
        $context->handler = 'test';
        $context->userType = 'anonymous';
        $context->setStartedAt();

        $query = new MonitoringQuery();
        $query->query = 'INSERT SOME STUFF';
        $query->parameters = [
            // deliberately create a broken string
            // see https://stackoverflow.com/questions/4663743/how-to-keep-json-encode-from-dropping-strings-with-invalid-characters
            '1' => mb_convert_encoding('Düsseldorf', 'ISO-8859-1', 'UTF-8'),
        ];
        $query->context = $context;
        $query->setStartedAt();
        $query->setEndedAt();
        $query->setDuration();

        $context->setEndedAt();
        $context->setDuration();
        $context->queryDurationMilliseconds = $query->getDuration();
        $context->twigRenderDurationMilliseconds = 0;
        $context->curlRequestDurationMilliseconds = 0;

        return ['query' => $query, 'context' => $context];
    }
}
