<?php

declare(strict_types=1);

namespace App\Entity\Contracts;

use App\Entity\User;

interface ReportInterface extends ContentInterface
{

    /**
     * @return 'entry_report'|'entry_comment_report'|'post_report'|'post_comment_report'|'message_report'
     */
    public function getReportType(): string;

    public function getId(): ?int;

    public function getUser(): ?User;

    public function getShortTitle(): string;
}
