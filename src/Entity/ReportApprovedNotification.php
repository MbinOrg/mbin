<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contracts\ReportInterface;
use App\Factory\Contract\ContentUrlFactory;
use App\Factory\Contract\ReportUrlFactory;
use App\Payloads\PushNotification;
use App\Service\SwitchingServiceRegistry;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Entity]
class ReportApprovedNotification extends Notification
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
        return 'report_approved_notification';
    }

    public function getMessage(TranslatorInterface $trans, string $locale, ContainerInterface $serviceContainer): PushNotification
    {
        /** @var SwitchingServiceRegistry $serviceRegistry */
        $serviceRegistry = $serviceContainer->get(SwitchingServiceRegistry::class);

        $subject = $this->report->getSubject();
        $linkToSubject = $this->getSubjectLink($this->report->getSubject(), $serviceRegistry);
        $linkToReport = $serviceRegistry->getService($this->report, ReportUrlFactory::class)->getReportUrl($this->report, Report::STATUS_APPROVED);
        if ($this->report->reporting->getId() === $this->user->getId()) {
            $title = $trans->trans('own_report_accepted', locale: $locale);
            $message = \sprintf('%s: %s', $trans->trans('report_subject', locale: $locale), $subject->getShortTitle());
            $actionUrl = $linkToSubject;
        } elseif ($this->report->reported->getId() === $this->user->getId()) {
            $title = $trans->trans('own_content_reported_accepted', locale: $locale);
            $message = \sprintf('%s: %s', $trans->trans('report_subject', locale: $locale), $subject->getShortTitle());
            $actionUrl = $linkToSubject;
        } else {
            $title = $trans->trans('report_accepted', locale: $locale);
            $message = \sprintf('%s: %s\n%s: %s\n%s: %s - %s',
                $trans->trans('reported_user', locale: $locale), $this->report->reported->username,
                $trans->trans('reporting_user', locale: $locale), $this->report->reporting->username,
                $trans->trans('report_subject', locale: $locale), $subject->getShortTitle(), $linkToSubject
            );
            $actionUrl = $linkToReport;
        }

        return new PushNotification($this->getId(), $message, $title, actionUrl: $actionUrl);
    }

    private function getSubjectLink(ReportInterface $subject, SwitchingServiceRegistry $serviceRegistry): string
    {
        try {
            return $serviceRegistry->getService($subject, ContentUrlFactory::class)->getLocalUrl($subject);
        } catch (\Exception) {
            return '';
        }
    }
}
