<?php

namespace App;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\MigratingSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler;

class MigratePdoToRedisSessionHandler extends MigratingSessionHandler
{
    public function __construct(PdoSessionHandler $currentHandler, RedisSessionHandler $writeOnlyHandler)
    {
        parent::__construct($currentHandler, $writeOnlyHandler);
    }
}
