<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\UserFilterList;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('filter_lists')]
class FilterListComponent
{
    public UserFilterList $filterList;
}
