<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contracts\ReportInterface;
use App\Payloads\PushNotification;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
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

    public function getMessage(TranslatorInterface $trans, string $locale, UrlGeneratorInterface $urlGenerator): PushNotification
    {
        /** @var Entry|EntryComment|Post|PostComment $subject */
        $subject = $this->report->getSubject();
        $message = \sprintf('%s: %s\n%s: %s',
            $trans->trans('reported_user', locale: $locale), $this->report->reported->username,
            $trans->trans('report_subject', locale: $locale), $subject->getShortTitle()
        );

        return new PushNotification($this->getId(), $message, $trans->trans('own_report_rejected', locale: $locale), actionUrl: $this->getSubjectLink($subject, $urlGenerator));
    }

    private function getSubjectLink(ReportInterface $subject, UrlGeneratorInterface $urlGenerator): string
    {
        if ($subject instanceof Entry) {
            return $urlGenerator->generate('entry_single', ['magazine_name' => $subject->magazine->name, 'entry_id' => $subject->getId(), 'slug' => $subject->slug]);
        } elseif ($subject instanceof EntryComment) {
            return $urlGenerator->generate('entry_comment_view', ['magazine_name' => $subject->magazine->name, 'entry_id' => $subject->entry->getId(), 'slug' => $subject->entry->slug, 'comment_id' => $subject->getId()]);
        } elseif ($subject instanceof Post) {
            return $urlGenerator->generate('post_single', ['magazine_name' => $subject->magazine->name, 'post_id' => $subject->getId(), 'slug' => $subject->slug]);
        } elseif ($subject instanceof PostComment) {
            return $urlGenerator->generate('post_single', ['magazine_name' => $subject->magazine->name, 'post_id' => $subject->post->getId(), 'slug' => $subject->post->slug]).'#post-comment-'.$subject->getId();
        }

        return '';
    }
}
