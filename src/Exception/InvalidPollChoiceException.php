<?php
declare(strict_types=1);

namespace App\Exception;

class InvalidPollChoiceException extends \Exception
{
    public function __construct(public string $pollChoice, public int $pollId, ?\Throwable $previous = null)
    {
        $message = "there is no choice '$pollChoice' on poll with id $pollId";
        parent::__construct($message, 0, $previous);
    }
}
