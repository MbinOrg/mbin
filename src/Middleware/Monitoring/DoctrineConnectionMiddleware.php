<?php

declare(strict_types=1);

namespace App\Middleware\Monitoring;

use App\Service\Monitor;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

/**
 * Heavily inspired by https://github.com/inspector-apm/inspector-symfony/blob/master/src/Doctrine/Middleware/V4/Connection.php.
 */
class DoctrineConnectionMiddleware extends AbstractConnectionMiddleware
{
    public function __construct(
        private readonly Monitor $monitor,
        Connection $wrappedConnection,
    ) {
        parent::__construct($wrappedConnection);
    }

    public function prepare(string $sql): Statement
    {
        return new DoctrineStatementMiddleware(parent::prepare($sql), $this->monitor, $sql);
    }

    public function query(string $sql): Result
    {
        if (!$this->monitor->shouldRecord() || null === $this->monitor->currentContext) {
            return parent::query($sql);
        }

        $this->monitor->startQuery($sql);

        try {
            return parent::query($sql);
        } finally {
            $this->monitor->endQuery();
        }
    }

    public function exec(string $sql): int
    {
        if (!$this->monitor->shouldRecord() || null === $this->monitor->currentContext) {
            return parent::exec($sql);
        }

        $this->monitor->startQuery($sql);

        try {
            return parent::exec($sql);
        } finally {
            $this->monitor->endQuery();
        }
    }

    public function beginTransaction(): void
    {
        if (!$this->monitor->shouldRecord() || null === $this->monitor->currentContext) {
            parent::beginTransaction();

            return;
        }

        $this->monitor->startQuery('START TRANSACTION');

        try {
            parent::beginTransaction();
        } finally {
            $this->monitor->endQuery();
        }
    }

    public function commit(): void
    {
        if (!$this->monitor->shouldRecord() || null === $this->monitor->currentContext) {
            parent::commit();

            return;
        }

        $this->monitor->startQuery('COMMIT');

        try {
            parent::commit();
        } finally {
            $this->monitor->endQuery();
        }
    }

    public function rollBack(): void
    {
        if (!$this->monitor->shouldRecord() || null === $this->monitor->currentContext) {
            parent::rollBack();

            return;
        }

        $this->monitor->startQuery('ROLLBACK');

        try {
            parent::rollBack();
        } finally {
            $this->monitor->endQuery();
        }
    }
}
