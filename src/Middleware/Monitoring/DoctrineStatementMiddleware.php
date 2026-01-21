<?php

declare(strict_types=1);

namespace App\Middleware\Monitoring;

use App\Service\Monitor;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;

/**
 * Heavily inspired by https://github.com/inspector-apm/inspector-symfony/blob/master/src/Doctrine/Middleware/V4/Statement.php.
 */
class DoctrineStatementMiddleware extends AbstractStatementMiddleware
{
    private array $parameters = [];

    public function __construct(
        protected readonly Statement $statement,
        private readonly Monitor $monitor,
        private string $sql,
    ) {
        parent::__construct($statement);
    }

    public function bindValue($param, $value, $type = ParameterType::STRING): bool
    {
        $this->parameters[$param] = $value;

        return parent::bindValue($param, $value, $type);
    }

    public function execute($params = null): Result
    {
        if (!$this->monitor->shouldRecord() || null === $this->monitor->currentContext) {
            return parent::execute($params);
        }

        $this->monitor->startQuery($this->sql, $this->parameters);

        try {
            return parent::execute($params);
        } finally {
            $this->monitor->endQuery();
        }
    }
}
