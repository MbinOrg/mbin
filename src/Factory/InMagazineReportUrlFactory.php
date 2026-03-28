<?php

namespace App\Factory;

use App\Entity\EntryCommentReport;
use App\Entity\EntryReport;
use App\Entity\PostCommentReport;
use App\Entity\PostReport;
use App\Factory\Contract\ReportUrlFactory;
use App\Service\Contracts\SwitchableService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @implements SwitchableService
 * @implements ReportUrlFactory<EntryReport|EntryCommentReport|PostReport|PostCommentReport>
 */
class InMagazineReportUrlFactory implements SwitchableService, ReportUrlFactory
{

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ){}

    public function getSupportedTypes(): array
    {
        return [EntryReport::class, EntryCommentReport::class, PostReport::class, PostCommentReport::class];
    }

    public function getReportUrl($report, string $status): string {
        return $this->urlGenerator->generate('magazine_panel_reports', ['name' => $report->magazine->name, 'status' => $status]).'#report-id-'.$report->getId();
    }
}
