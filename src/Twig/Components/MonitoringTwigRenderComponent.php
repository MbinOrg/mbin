<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\MonitoringTwigRender;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('monitoring_twig_render')]
class MonitoringTwigRenderComponent
{
    public MonitoringTwigRender $render;
}
