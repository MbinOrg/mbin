<?php

declare(strict_types=1);

namespace App\Payloads\NodeInfo;

class NodeInfoUsage
{
    public NodeInfoUsageUsers $users;
    public int $localPosts;
    public int $localComments;
}
