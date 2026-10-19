<?php
declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class InvalidApObjectException extends UnrecoverableMessageHandlingException
{
public function __construct(public string $messageStart = '', public array $apObject, int $code = 0, ?\Throwable $previous = null)
{
    if (!$apObject['id']) {
        $objStr = '(object) '.json_encode($apObject);
    } else {
        $objStr = '(id) '.$apObject['id'];
    }
    parent::__construct($this->messageStart.': AP object = '.$objStr, $code, $previous);
}
}
