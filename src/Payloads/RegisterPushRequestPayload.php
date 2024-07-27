<?php

declare(strict_types=1);

namespace App\Payloads;

class RegisterPushRequestPayload
{
    public string $endpoint;
    public string $serverKey;
    public string $deviceKey;
    public string $contentPublicKey;
}
