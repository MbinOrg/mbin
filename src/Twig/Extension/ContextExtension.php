<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Twig\Runtime\ContextExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ContextExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_route_name', [ContextExtensionRuntime::class, 'isRouteName']),
            new TwigFunction('is_route_name_contains', [ContextExtensionRuntime::class, 'isRouteNameContains']),
            new TwigFunction('is_route_name_starts_with', [ContextExtensionRuntime::class, 'isRouteNameStartsWith']),
            new TwigFunction('is_route_name_end_with', [ContextExtensionRuntime::class, 'isRouteNameEndWith']),
            new TwigFunction('route_has_param', [ContextExtensionRuntime::class, 'routeHasParam']),
            new TwigFunction('route_param_exists', [ContextExtensionRuntime::class, 'routeParamExists']),
            new TwigFunction('get_active_sort_option', [ContextExtensionRuntime::class, 'getActiveSortOption']),
            new TwigFunction('get_active_sort_option_for_comments', [ContextExtensionRuntime::class, 'getActiveSortOptionForComments']),
            new TwigFunction('get_default_sort_option', [ContextExtensionRuntime::class, 'getDefaultSortOption']),
            new TwigFunction('get_default_sort_option_for_comments', [ContextExtensionRuntime::class, 'getDefaultSortOptionForComments']),
            new TwigFunction('is_route_params_contains', [ContextExtensionRuntime::class, 'isRouteParamsContains']),
            new TwigFunction('get_route_param', [ContextExtensionRuntime::class, 'getRouteParam']),
            new TwigFunction('get_time_param_translated', [ContextExtensionRuntime::class, 'getTimeParamTranslated']),
        ];
    }
}
