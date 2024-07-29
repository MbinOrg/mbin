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
class MagazineBanNotification extends Notification
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
        return 'magazine_ban_notification';
    }

    public function getMessage(TranslatorInterface $trans, string $locale, UrlGeneratorInterface $urlGenerator): PushNotification
    {
        $intl = new \IntlDateFormatter($trans->getLocale(), \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT, calendar: \IntlDateFormatter::GREGORIAN);

        $message = \sprintf('%s %s %s %s. %s %s. %s: %s',
            $this->ban->bannedBy->username,
            $trans->trans('banned'),
            $trans->trans('from'),
            $this->ban->magazine->name,
            $trans->trans('ban_expired'),
            $intl->format($this->ban->expiredAt),
            $trans->trans('reason'),
            $this->ban->reason
        );
        $slash = $this->ban->bannedBy->avatar && !str_starts_with('/', $this->ban->bannedBy->avatar->filePath) ? '/' : '';
        $avatarUrl = $this->ban->bannedBy->avatar ? '/media/cache/resolve/avatar_thumb'.$slash.$this->ban->bannedBy->avatar->filePath : null;

        return new PushNotification($message, $trans->trans('notification_title_ban', locale: $locale), avatarUrl: $avatarUrl);
    }
}
