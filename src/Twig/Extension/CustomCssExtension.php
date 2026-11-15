<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Twig\Runtime\CustomCssRuntime;
use App\Twig\Runtime\DomainExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CustomCssExtension extends AbstractExtension
{
    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('subject_custom_css_marker', [CustomCssRuntime::class, 'subjectCustomCssMarker']),
        ];
    }
}
