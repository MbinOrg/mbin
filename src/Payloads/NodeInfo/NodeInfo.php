<?php

declare(strict_types=1);

namespace App\Payloads\NodeInfo;

class NodeInfo
{
    public string $version;
    public NodeInfoSoftware $software;
    /** @var string[] */
    public array $protocols;
    public bool $openRegistrations;
    public NodeInfoUsage $usage;
    public NodeInfoServices $services;
}
