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
class EntryCreatedNotification extends Notification
{
    #[ManyToOne(targetEntity: Entry::class, inversedBy: 'notifications')]
    #[JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Entry $entry = null;

    public function __construct(User $receiver, Entry $entry)
    {
        parent::__construct($receiver);

        $this->entry = $entry;
    }

    public function getSubject(): Entry
    {
        return $this->entry;
    }

    public function getType(): string
    {
        return 'entry_created_notification';
    }

    public function getMessage(TranslatorInterface $trans, string $locale, UrlGeneratorInterface $urlGenerator): PushNotification
    {
        $message = \sprintf('%s %s - %s', $this->entry->user->username, $trans->trans('added_new_thread'), $this->entry->getShortTitle());
        $slash = $this->entry->user->avatar && !str_starts_with('/', $this->entry->user->avatar->filePath) ? '/' : '';
        $avatarUrl = $this->entry->user->avatar ? '/media/cache/resolve/avatar_thumb'.$slash.$this->entry->user->avatar->filePath : null;
        $url = $urlGenerator->generate('entry_single', [
            'entry_id' => $this->entry->getId(),
            'magazine_name' => $this->entry->magazine->name,
            'slug' => $this->entry->slug ?? '-',
        ]);

        return new PushNotification($this->getId(), $message, $trans->trans('notification_title_new_thread', locale: $locale), actionUrl: $url, avatarUrl: $avatarUrl);
    }
}
