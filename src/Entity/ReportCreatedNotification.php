<?php

declare(strict_types=1);

namespace App\Entity;

use App\Payloads\PushNotification;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

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

    public function getMessage(TranslatorInterface $trans, string $locale, UrlGeneratorInterface $urlGenerator): PushNotification
    {
        /** @var Entry|EntryComment|Post|PostComment $subject */
        $subject = $this->report->getSubject();
        $reportLink = $urlGenerator->generate('magazine_panel_reports', ['name' => $this->report->magazine->name, 'status' => Report::STATUS_PENDING]).'#report-id-'.$this->report->getId();
        $message = sprintf('%s %s %s\n%s: %s', $this->report->reporting->username, $trans->trans('reported', locale: $locale), $this->report->reported->username,
            $trans->trans('report_subject', locale: $locale), $subject->getShortTitle());

        return new PushNotification($message, $trans->trans('notification_title_new_report'), actionUrl: $reportLink);
    }
}
