<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\PageView\ContentPageView;
use App\Pagination\Cursor\CursorPagination;
use App\Pagination\Cursor\CursorPaginationInterface;
use App\Pagination\Cursor\NativeQueryCursorAdapter;
use App\Repository\Criteria;
use App\Tests\WebTestCase;

class CursorPaginationTest extends WebTestCase
{
    private CursorPaginationInterface $cursorPagination;

    private array $createdEntries = [];

    public function testCursorPaginationInteger(): void
    {
        $this->simpleSetUp();
        $this->cursorPagination->setCurrentPage(-1);
        $currentPage = $this->cursorPagination->getCurrentPageResults();

        $i = 0;
        foreach ($currentPage as $result) {
            self::assertEquals($i, $result['value']);
            ++$i;
        }
        self::assertEquals(3, $i);

        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertFalse($this->cursorPagination->hasPreviousPage());

        $this->cursorPagination->setCurrentPage($this->cursorPagination->getNextPage()[0]);
        $currentPage = $this->cursorPagination->getCurrentPageResults();

        $i = 3;
        foreach ($currentPage as $result) {
            self::assertEquals($i, $result['value']);
            ++$i;
        }
        self::assertEquals(6, $i);

        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertTrue($this->cursorPagination->hasPreviousPage());

        $this->cursorPagination->setCurrentPage($this->cursorPagination->getNextPage()[0]);
        $currentPage = $this->cursorPagination->getCurrentPageResults();

        $i = 6;
        foreach ($currentPage as $result) {
            self::assertEquals($i, $result['value']);
            ++$i;
        }
        self::assertEquals(9, $i);

        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertTrue($this->cursorPagination->hasPreviousPage());

        $this->cursorPagination->setCurrentPage($this->cursorPagination->getNextPage()[0]);
        $currentPage = $this->cursorPagination->getCurrentPageResults();

        $i = 9;
        foreach ($currentPage as $result) {
            self::assertEquals($i, $result['value']);
            ++$i;
        }
        self::assertEquals(10, $i);

        self::assertFalse($this->cursorPagination->hasNextPage());
        self::assertTrue($this->cursorPagination->hasPreviousPage());

        $this->cursorPagination->setCurrentPage($this->cursorPagination->getPreviousPage()[0]);
        $currentPage = $this->cursorPagination->getCurrentPageResults();

        $i = 6;
        foreach ($currentPage as $result) {
            self::assertEquals($i, $result['value']);
            ++$i;
        }
        self::assertEquals(9, $i);

        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertTrue($this->cursorPagination->hasPreviousPage());

        $this->cursorPagination->setCurrentPage($this->cursorPagination->getPreviousPage()[0]);
        $currentPage = $this->cursorPagination->getCurrentPageResults();

        $i = 3;
        foreach ($currentPage as $result) {
            self::assertEquals($i, $result['value']);
            ++$i;
        }
        self::assertEquals(6, $i);

        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertTrue($this->cursorPagination->hasPreviousPage());

        $this->cursorPagination->setCurrentPage($this->cursorPagination->getPreviousPage()[0]);
        $currentPage = $this->cursorPagination->getCurrentPageResults();

        $i = 0;
        foreach ($currentPage as $result) {
            self::assertEquals($i, $result['value']);
            ++$i;
        }
        self::assertEquals(3, $i);
        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertFalse($this->cursorPagination->hasPreviousPage());
    }

    public function testCursorPaginationEdgeCase(): void
    {
        $this->confusingSetUp();
        $this->cursorPagination->setCurrentPage(-1);
        $currentPage = $this->cursorPagination->getCurrentPageResults();

        self::assertEquals(0, $currentPage[0]['value']);
        self::assertEquals(0, $currentPage[0]['value2']);
        self::assertEquals(0, $currentPage[1]['value']);
        self::assertEquals(1, $currentPage[1]['value2']);
        self::assertEquals(0, $currentPage[2]['value']);
        self::assertEquals(2, $currentPage[2]['value2']);

        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertFalse($this->cursorPagination->hasPreviousPage());

        $cursors = $this->cursorPagination->getNextPage();
        self::assertEquals([0, 2], $cursors);
        $this->cursorPagination->setCurrentPage($cursors[0], $cursors[1]);
        $currentPage = $this->cursorPagination->getCurrentPageResults();

        self::assertEquals(0, $currentPage[0]['value']);
        self::assertEquals(3, $currentPage[0]['value2']);
        self::assertEquals(0, $currentPage[1]['value']);
        self::assertEquals(4, $currentPage[1]['value2']);
        self::assertEquals(1, $currentPage[2]['value']);
        self::assertEquals(5, $currentPage[2]['value2']);

        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertTrue($this->cursorPagination->hasPreviousPage());

        $cursors = $this->cursorPagination->getNextPage();
        self::assertEquals([1, 5], $cursors);
        $this->cursorPagination->setCurrentPage($cursors[0], $cursors[1]);
        $currentPage = $this->cursorPagination->getCurrentPageResults();

        self::assertEquals(1, $currentPage[0]['value']);
        self::assertEquals(6, $currentPage[0]['value2']);
        self::assertEquals(1, $currentPage[1]['value']);
        self::assertEquals(7, $currentPage[1]['value2']);
        self::assertEquals(1, $currentPage[2]['value']);
        self::assertEquals(8, $currentPage[2]['value2']);

        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertTrue($this->cursorPagination->hasPreviousPage());

        $cursors = $this->cursorPagination->getPreviousPage();
        self::assertEquals([0, 2], $cursors);
        $this->cursorPagination->setCurrentPage($cursors[0], $cursors[1]);
        $currentPage = $this->cursorPagination->getCurrentPageResults();

        self::assertEquals(0, $currentPage[0]['value']);
        self::assertEquals(3, $currentPage[0]['value2']);
        self::assertEquals(0, $currentPage[1]['value']);
        self::assertEquals(4, $currentPage[1]['value2']);
        self::assertEquals(1, $currentPage[2]['value']);
        self::assertEquals(5, $currentPage[2]['value2']);

        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertTrue($this->cursorPagination->hasPreviousPage());

        $cursors = $this->cursorPagination->getPreviousPage();
        self::assertEquals([0, -1], $cursors);
        $this->cursorPagination->setCurrentPage($cursors[0], $cursors[1]);
        $currentPage = $this->cursorPagination->getCurrentPageResults();

        self::assertEquals(0, $currentPage[0]['value']);
        self::assertEquals(0, $currentPage[0]['value2']);
        self::assertEquals(0, $currentPage[1]['value']);
        self::assertEquals(1, $currentPage[1]['value2']);
        self::assertEquals(0, $currentPage[2]['value']);
        self::assertEquals(2, $currentPage[2]['value2']);
        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertFalse($this->cursorPagination->hasPreviousPage());
    }

    public function simpleSetUp(): void
    {
        $tempTable = 'CREATE TEMPORARY TABLE cursorTest (value INT)';
        $this->entityManager->getConnection()->executeQuery($tempTable);

        for ($i = 0; $i < 10; ++$i) {
            $this->entityManager->getConnection()->executeQuery("INSERT INTO cursorTest(value) VALUES($i)");
        }

        $sql = 'SELECT * FROM cursorTest WHERE %cursor% ORDER BY %cursorSort%';

        $this->cursorPagination = new CursorPagination(
            new NativeQueryCursorAdapter(
                $this->entityManager->getConnection(),
                $sql,
                'value > :cursor',
                'value <= :cursor',
                'value',
                'value DESC',
                [],
            ),
            'value',
            3
        );
    }

    public function confusingSetUp(): void
    {
        $tempTable = 'CREATE TEMPORARY TABLE cursorTest (value INT, value2 INT)';
        $this->entityManager->getConnection()->executeQuery($tempTable);

        $this->entityManager->getConnection()->executeQuery('INSERT INTO cursorTest(value, value2) VALUES (0, 0), (0, 1), (0, 2), (0, 3), (0, 4), (1, 5), (1, 6), (1, 7), (1, 8), (1, 9)');

        $sql = 'SELECT * FROM cursorTest WHERE %cursor% OR (%cursor2%) ORDER BY %cursorSort%, %cursorSort2%';

        $this->cursorPagination = new CursorPagination(
            new NativeQueryCursorAdapter(
                $this->entityManager->getConnection(),
                $sql,
                'value > :cursor',
                'value < :cursor',
                'value',
                'value DESC',
                [],
                'value = :cursor AND value2 > :cursor2',
                'value = :cursor AND value2 <= :cursor2',
                'value2',
                'value2 DESC',
            ),
            'value',
            3,
            'value2'
        );
    }

    public function realSetUp(): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $entry = $this->getEntryByTitle("Entry $i");
            $ii = 10 - $i;
            $entry->createdAt = new \DateTimeImmutable("now - $ii minutes");
            // for debugging purposes
            $this->createdEntries[$i] = "$entry->title | {$entry->createdAt->format(DATE_ATOM)}";
        }
        $this->entityManager->flush();
    }

    public function testRealPagination(): void
    {
        $this->realSetUp();

        $criteria = new ContentPageView(1, $this->security);
        $criteria->sortOption = Criteria::SORT_COMMENTED;
        $criteria->perPage = 3;
        $cursor = $this->contentRepository->guessInitialCursor($criteria->sortOption);
        $cursor2 = $this->contentRepository->guessInitialCursor(Criteria::SORT_NEW);
        $this->cursorPagination = $this->contentRepository->findByCriteriaCursored($criteria, $cursor, $cursor2);
        $results = $this->cursorPagination->getCurrentPageResults();

        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertFalse($this->cursorPagination->hasPreviousPage());

        self::assertEquals('Entry 9', $results[0]->title);
        self::assertEquals('Entry 8', $results[1]->title);
        self::assertEquals('Entry 7', $results[2]->title);

        $cursors = $this->cursorPagination->getNextPage();
        $this->cursorPagination->setCurrentPage($cursors[0], $cursors[1]);
        $results = $this->cursorPagination->getCurrentPageResults();

        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertTrue($this->cursorPagination->hasPreviousPage());

        self::assertEquals('Entry 6', $results[0]->title);
        self::assertEquals('Entry 5', $results[1]->title);
        self::assertEquals('Entry 4', $results[2]->title);

        $cursors = $this->cursorPagination->getNextPage();
        $this->cursorPagination->setCurrentPage($cursors[0], $cursors[1]);
        $results = $this->cursorPagination->getCurrentPageResults();

        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertTrue($this->cursorPagination->hasPreviousPage());

        self::assertEquals('Entry 3', $results[0]->title);
        self::assertEquals('Entry 2', $results[1]->title);
        self::assertEquals('Entry 1', $results[2]->title);

        $cursors = $this->cursorPagination->getNextPage();
        $this->cursorPagination->setCurrentPage($cursors[0], $cursors[1]);
        $results = $this->cursorPagination->getCurrentPageResults();

        self::assertFalse($this->cursorPagination->hasNextPage());
        self::assertTrue($this->cursorPagination->hasPreviousPage());

        self::assertEquals('Entry 0', $results[0]->title);

        $cursors = $this->cursorPagination->getPreviousPage();
        $this->cursorPagination->setCurrentPage($cursors[0], $cursors[1]);
        $results = $this->cursorPagination->getCurrentPageResults();

        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertTrue($this->cursorPagination->hasPreviousPage());

        self::assertEquals('Entry 3', $results[0]->title);
        self::assertEquals('Entry 2', $results[1]->title);
        self::assertEquals('Entry 1', $results[2]->title);

        $cursors = $this->cursorPagination->getPreviousPage();
        $this->cursorPagination->setCurrentPage($cursors[0], $cursors[1]);
        $results = $this->cursorPagination->getCurrentPageResults();

        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertTrue($this->cursorPagination->hasPreviousPage());

        self::assertEquals('Entry 6', $results[0]->title);
        self::assertEquals('Entry 5', $results[1]->title);
        self::assertEquals('Entry 4', $results[2]->title);

        $cursors = $this->cursorPagination->getPreviousPage();
        $this->cursorPagination->setCurrentPage($cursors[0], $cursors[1]);
        $results = $this->cursorPagination->getCurrentPageResults();

        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertFalse($this->cursorPagination->hasPreviousPage());

        self::assertEquals('Entry 9', $results[0]->title);
        self::assertEquals('Entry 8', $results[1]->title);
        self::assertEquals('Entry 7', $results[2]->title);
    }
}
