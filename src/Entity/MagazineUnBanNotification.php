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
class MagazineUnBanNotification extends Notification
{
    #[ManyToOne(targetEntity: MagazineBan::class)]
    #[JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?MagazineBan $ban = null;

    public function __construct(User $receiver, MagazineBan $ban)
    {
        parent::__construct($receiver);

        $this->ban = $ban;
    }

    public function getSubject(): MagazineBan
    {
        return $this->ban;
    }

    public function getType(): string
    {
        return 'magazine_unban_notification';
    }

    public function getMessage(TranslatorInterface $trans, string $locale, UrlGeneratorInterface $urlGenerator): PushNotification
    {
        $message = $trans->trans('you_are_no_longer_banned_from_magazine', ['%m' => $this->ban->magazine->name]);
        $slash = $this->ban->magazine->icon && !str_starts_with('/', $this->ban->magazine->icon->filePath) ? '/' : '';
        $avatarUrl = $this->ban->magazine->icon ? '/media/cache/resolve/avatar_thumb'.$slash.$this->ban->magazine->icon->filePath : null;

        return new PushNotification($this->getId(), $message, $trans->trans('notification_title_ban', locale: $locale), avatarUrl: $avatarUrl);
    }
}
