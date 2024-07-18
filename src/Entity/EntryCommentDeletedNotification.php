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
class EntryCommentDeletedNotification extends Notification
{
    #[ManyToOne(targetEntity: EntryComment::class, inversedBy: 'notifications')]
    #[JoinColumn(nullable: true)]
    public ?EntryComment $entryComment = null;

    public function __construct(User $receiver, EntryComment $comment)
    {
        parent::__construct($receiver);

        $this->entryComment = $comment;
    }

    public function getSubject(): EntryComment
    {
        return $this->entryComment;
    }

    public function getComment(): EntryComment
    {
        return $this->entryComment;
    }

    public function getType(): string
    {
        return 'entry_comment_deleted_notification';
    }

    public function getMessage(TranslatorInterface $trans, string $locale, UrlGeneratorInterface $urlGenerator): PushNotification
    {
        $message = sprintf('%s %s - %s', $trans->trans('comment'), $this->entryComment->getShortTitle(), $this->entryComment->isTrashed() ? $trans->trans('removed') : $trans->trans('deleted'));
        $slash = $this->entryComment->user->avatar && !str_starts_with('/', $this->entryComment->user->avatar->filePath) ? '/' : '';
        $avatarUrl = $this->entryComment->user->avatar ? '/media/cache/resolve/avatar_thumb'.$slash.$this->entryComment->user->avatar->filePath : null;
        $url = $urlGenerator->generate('entry_comment_view', [
            'entry_id' => $this->entryComment->entry->getId(),
            'magazine_name' => $this->entryComment->magazine->name,
            'slug' => $this->entryComment->entry->slug ?? '-',
            'comment_id' => $this->entryComment->getId(),
        ]);

        return new PushNotification($message, $trans->trans('notification_title_removed_comment', locale: $locale), actionUrl: $url, avatarUrl: $avatarUrl);
    }
}
