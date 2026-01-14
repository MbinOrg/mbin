<?php

declare(strict_types=1);

namespace App\Middleware\Monitoring;

use App\Service\Monitor;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * Heavily inspired by https://github.com/inspector-apm/inspector-symfony/blob/master/src/Doctrine/Middleware/InspectorDriver.php.
 */
class DoctrineDriverMiddleware extends AbstractDriverMiddleware
{
    public function __construct(
        private readonly Monitor $monitor,
        Driver $wrappedDriver,
    ) {
        parent::__construct($wrappedDriver);
    }

    public function connect(#[\SensitiveParameter] array $params)
    {
        $connection = parent::connect($params);

        return new DoctrineConnectionMiddleware($this->monitor, $connection);
    }
}
