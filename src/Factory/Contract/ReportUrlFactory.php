<?php

namespace App\Factory\Contract;

use App\Entity\Report;

/**
 * @template T of Report
 */
interface ReportUrlFactory
{
    /**
     * @param T $report
     * @return string the absolute URL to the report
     */
    public function getReportUrl($report, string $status): string;
}
