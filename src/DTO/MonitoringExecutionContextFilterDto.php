<?php

declare(strict_types=1);

namespace App\DTO;

class MonitoringExecutionContextFilterDto
{
    public ?string $executionType = null;

    public ?string $path = null;

    public ?string $handler = null;

    public ?string $userType = null;

    public ?bool $hasException = null;

    public ?\DateTimeImmutable $createdFrom = null;

    public ?\DateTimeImmutable $createdTo = null;

    public ?float $durationMinimum = null;
}
