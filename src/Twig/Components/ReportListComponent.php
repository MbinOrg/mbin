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
    public ?string $requestedStatus;

    public function __construct(
        RequestStack $requestStack,
    ) {
        $this->requestedStatus = Polyfills::requestParam($requestStack->getCurrentRequest(), 'status', null);
    }

}
