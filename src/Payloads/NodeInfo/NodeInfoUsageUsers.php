<?php

declare(strict_types=1);

namespace App\Payloads\NodeInfo;

class NodeInfoUsageUsers
{
    public int $total;
    public ?int $activeHalfYear = 0;
    public ?int $activeMonth = 0;
}
