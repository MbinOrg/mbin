<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Twig\Runtime\FrontExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class FrontExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('front_options_url', [FrontExtensionRuntime::class, 'frontOptionsUrl']),
            new TwigFunction('get_class', [FrontExtensionRuntime::class, 'getClass']),
            new TwigFunction('get_subject_type', [FrontExtensionRuntime::class, 'getSubjectType']),
        ];
    }
}
