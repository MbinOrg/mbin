<?php

declare(strict_types=1);

namespace App\Middleware\Monitoring;

use App\Service\Monitor;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

/**
 * Heavily inspired by https://github.com/inspector-apm/inspector-symfony/blob/master/src/Doctrine/Middleware/InspectorMiddleware.php.
 */
class DoctrineMiddleware implements Middleware
{
    public function __construct(
        protected readonly Monitor $monitor,
    ) {
    }

    public function wrap(Driver $driver): Driver
    {
        return new DoctrineDriverMiddleware($this->monitor, $driver);
    }
}
