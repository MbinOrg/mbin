<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Utils\Polyfills;
use Pagerfanta\PagerfantaInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('report_list', template: 'components/report_list.html.twig')]
final class ReportListComponent
{
    public PagerfantaInterface $reports;
    public string $routeName = 'admin_reports';
    public ?string $magazineName = null;

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {}

    public function getStatus(): string {
        return Polyfills::requestParam($this->requestStack->getCurrentRequest(), 'status');
    }

}
