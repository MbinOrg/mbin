<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity]
class ReportCreatedNotification extends Notification
{
    #[ManyToOne(targetEntity: Report::class)]
    #[JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Report $report = null;

    public function __construct(User $receiver, Report $report)
    {
        parent::__construct($receiver);

        $this->report = $report;
    }

    public function getType(): string
    {
        return 'report_created_notification';
    }
}
