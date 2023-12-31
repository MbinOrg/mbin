<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Twig\Runtime\FormattingExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class FormattingExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('markdown', [FormattingExtensionRuntime::class, 'convertToHtml']),
            new TwigFilter('bool', fn ($value) => (bool) $value),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_short_sentence', [FormattingExtensionRuntime::class, 'getShortSentence']),
        ];
    }
}
