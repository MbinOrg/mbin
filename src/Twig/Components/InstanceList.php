<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Instance;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('instance_list')]
class InstanceList
{
    /** @var Instance[] */
    public array $instances;

    /** @var Instance[] */
    public ?array $blockedInstances = null;

    /** @var Instance[] */
    public ?array $globallyBlockedInstances = null;

    public bool $showUnBanButton = false;

    public bool $showBanButton = false;

    public bool $showDenyButton = false;

    public bool $showAllowButton = false;

    public bool $showAdminBlockButton = false;
}
