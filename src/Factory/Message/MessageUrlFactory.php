<?php

namespace App\Factory\Message;

use App\Entity\Message;
use App\Entity\Report;
use App\Factory\Contract\ContentUrlFactory;
use App\Factory\Contract\ReportUrlFactory;
use App\Service\Contracts\SwitchableService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @implements SwitchableService
 * @implements ContentUrlFactory<Message>
 * @implements ReportUrlFactory<Message>
 */
readonly class MessageUrlFactory implements SwitchableService, ContentUrlFactory, ReportUrlFactory
{

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ){}

    public function getSupportedTypes(): array
    {
        return [Message::class];
    }

    public function getActivityPubId($subject): string
    {
        return $this->urlGenerator->generate('ap_message', ['uuid' => $subject->uuid], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function getLocalUrl($subject): string
    {
        return $this->urlGenerator->generate('messages_single', ['id' => $subject->thread->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function getReportUrl($report, string $status): string
    {
        return $this->urlGenerator->generate('message_reports', ['status' => $status]).'#report-id-'.$report->getId();
    }
}
