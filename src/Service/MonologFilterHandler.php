<?php

namespace App\Service;

use Monolog\Handler\AbstractHandler;
use Monolog\LogRecord;

class MonologFilterHandler extends AbstractHandler
{

    private const array TO_IGNORE = [
        'User Deprecated: Since symfony/http-foundation 7.4: Request::get() is deprecated, '
    ];

    public function isHandling(LogRecord $record): bool
    {
        return true;
    }

    public function handle(LogRecord $record): bool
    {
        return $this->shouldFilter($record);
    }

    private function shouldFilter(LogRecord $record): bool
    {
        foreach (self::TO_IGNORE as $str) {
            if(str_contains($record->message, $str)) {
                return true;
            }
        }
        return false;
    }
}
