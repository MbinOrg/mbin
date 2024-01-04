<?php

declare(strict_types=1);

namespace App\Entity\Contracts;

interface ApiResourceInterface
{
    public function getId(): ?int;

    public function getApId(): ?string;
}
