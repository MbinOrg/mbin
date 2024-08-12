<?php

declare(strict_types=1);

namespace App\DTO;

class ServerPublicKeyDto
{
    public function __construct(
        public string $serverPublicKey,
    ) {
    }
}
