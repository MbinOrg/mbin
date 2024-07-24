<?php

declare(strict_types=1);

namespace App\DTO;

use OpenApi\Attributes as OA;

class NotificationPushSubscriptionRequestDto
{
    #[OA\Property(description: "The URL of the push endpoint messages will be sent to, normally you'll get this address when you register your application on a push service")]
    public string $endpoint;
    #[OA\Property(description: "On web push this would be called the 'auth' key, which is used to authenticate the server to the push service. According to https://web-push-book.gauntface.com/web-push-protocol/ this is a 'just' a 'secret'")]
    public string $serverKey;
    #[OA\Property(description: 'The public key of your key pair (client public key), which is used to encrypt the content. This should be a ECDH, p256 key')]
    public string $contentPublicKey;
}
