<?php

declare(strict_types=1);

namespace App\Payloads\NodeInfo;

class NodeInfo
{
    public ?string $version = null;
    public ?NodeInfoSoftware $software = null;
    /** @var string[] */
    public ?array $protocols = null;
    public bool $openRegistrations = false;
    public ?NodeInfoUsage $usage = null;
    public ?NodeInfoServices $services = null;
}
