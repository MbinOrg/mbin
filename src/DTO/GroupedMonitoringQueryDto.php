<?php

declare(strict_types=1);

namespace App\DTO;

class GroupedMonitoringQueryDto
{
    public string $query;
    public float $minExecutionTime;
    public float $maxExecutionTime;
    public float $meanExecutionTime;
    public float $totalExecutionTime;
    public int $count;
}
