<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Pagination\Cursor\CursorPagination;
use App\Pagination\Cursor\CursorPaginationInterface;
use App\Pagination\Cursor\NativeQueryCursorAdapter;
use App\Tests\WebTestCase;

class CursorPaginationTest extends WebTestCase
{
    private CursorPaginationInterface $cursorPagination;

    public function setUp(): void
    {
        parent::setUp();

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

    public function testCursorPaginationInteger(): void
    {
        $this->cursorPagination->setCurrentPage(-1);
        $currentPage = $this->cursorPagination->getCurrentPageResults();

        $i = 0;
        foreach ($currentPage as $result) {
            self::assertEquals($i, $result['value']);
            ++$i;
        }

        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertFalse($this->cursorPagination->hasPreviousPage());

        $this->cursorPagination->setCurrentPage($this->cursorPagination->getNextPage());
        $currentPage = $this->cursorPagination->getCurrentPageResults();

        $i = 3;
        foreach ($currentPage as $result) {
            self::assertEquals($i, $result['value']);
            ++$i;
        }

        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertTrue($this->cursorPagination->hasPreviousPage());

        $this->cursorPagination->setCurrentPage($this->cursorPagination->getNextPage());
        $currentPage = $this->cursorPagination->getCurrentPageResults();

        $i = 6;
        foreach ($currentPage as $result) {
            self::assertEquals($i, $result['value']);
            ++$i;
        }

        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertTrue($this->cursorPagination->hasPreviousPage());

        $this->cursorPagination->setCurrentPage($this->cursorPagination->getNextPage());
        $currentPage = $this->cursorPagination->getCurrentPageResults();

        $i = 9;
        foreach ($currentPage as $result) {
            self::assertEquals($i, $result['value']);
            ++$i;
        }

        self::assertFalse($this->cursorPagination->hasNextPage());
        self::assertTrue($this->cursorPagination->hasPreviousPage());

        $this->cursorPagination->setCurrentPage($this->cursorPagination->getPreviousPage());
        $currentPage = $this->cursorPagination->getCurrentPageResults();

        $i = 6;
        foreach ($currentPage as $result) {
            self::assertEquals($i, $result['value']);
            ++$i;
        }

        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertTrue($this->cursorPagination->hasPreviousPage());

        $this->cursorPagination->setCurrentPage($this->cursorPagination->getPreviousPage());
        $currentPage = $this->cursorPagination->getCurrentPageResults();

        $i = 3;
        foreach ($currentPage as $result) {
            self::assertEquals($i, $result['value']);
            ++$i;
        }

        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertTrue($this->cursorPagination->hasPreviousPage());

        $this->cursorPagination->setCurrentPage($this->cursorPagination->getPreviousPage());
        $currentPage = $this->cursorPagination->getCurrentPageResults();

        $i = 0;
        foreach ($currentPage as $result) {
            self::assertEquals($i, $result['value']);
            ++$i;
        }
        self::assertTrue($this->cursorPagination->hasNextPage());
        self::assertFalse($this->cursorPagination->hasPreviousPage());
    }
}
