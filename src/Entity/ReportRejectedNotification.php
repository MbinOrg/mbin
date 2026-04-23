<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contracts\ReportInterface;
use App\Factory\Contract\ContentUrlFactory;
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
class ReportRejectedNotification extends Notification
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
        return 'report_rejected_notification';
    }

    public function getMessage(TranslatorInterface $trans, string $locale, ContainerInterface $serviceContainer): PushNotification
    {
        /** @var SwitchingServiceRegistry $serviceRegistry */
        $serviceRegistry = $serviceContainer->get(SwitchingServiceRegistry::class);

        $subject = $this->report->getSubject();
        $message = \sprintf('%s: %s\n%s: %s',
            $trans->trans('reported_user', locale: $locale), $this->report->reported->username,
            $trans->trans('report_subject', locale: $locale), $subject->getShortTitle()
        );

        return new PushNotification(
            $this->getId(),
            $message,
            $trans->trans('own_report_rejected', locale: $locale),
            actionUrl: $this->getSubjectLink($subject, $serviceRegistry)
        );
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
