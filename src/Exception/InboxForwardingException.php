<?php

declare(strict_types=1);

namespace App\Exception;

use Exception;

/**
 * This exception is thrown when a remote instance A sends an activity message with an actor from instance B
 * and the activity has an audience from instance A as a target audience. @see https://www.w3.org/TR/activitypub/#inbox-forwarding
 * It indicates that the post should not be added from the activity, but fetched from the original instance.
 */
class InboxForwardingException extends \Exception
{
    /**
     * @param string $receivedFrom the domain from which the activity was received
     * @param string $realOrigin   the original url where the activity can be found
     */
    public function __construct(public string $receivedFrom, public string $realOrigin, int $code = 0, ?\Throwable $previous = null)
    {
        $message = "Received a message from '$receivedFrom' which originated from '$realOrigin'. Though a audience on '$receivedFrom' was targeted and the post therefore forwarded.";
        parent::__construct($message, $code, $previous);
    }
}
