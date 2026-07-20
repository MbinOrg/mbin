<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contracts\ReportInterface;
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

    public function getMessage(TranslatorInterface $trans, string $locale, ContainerInterface $serviceContainer): PushNotification
    {
        /** @var SwitchingServiceRegistry $serviceRegistry */
        $serviceRegistry = $serviceContainer->get(SwitchingServiceRegistry::class);

        $subject = $this->report->getSubject();
        $reportLink = $serviceRegistry->getService($this->report, ReportUrlFactory::class)->getReportUrl($this->report, Report::STATUS_PENDING);
        $message = \sprintf('%s %s %s\n%s: %s',
            $this->report->reporting->username,
            $trans->trans('reported', locale: $locale),
            $this->report->reported->username,
            $trans->trans('report_subject', locale: $locale),
            $subject->getShortTitle()
        );

        return new PushNotification($this->getId(), $message, $trans->trans('notification_title_new_report'), actionUrl: $reportLink);
    }
}
