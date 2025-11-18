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
        $intl = new \IntlDateFormatter($locale, \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT, calendar: \IntlDateFormatter::GREGORIAN);

        if ($this->ban->expiredAt) {
            $message = \sprintf('%s %s: %s. %s: %s',
                $trans->trans('you_have_been_banned_from_magazine', ['%m' => $this->ban->magazine->name]),
                new \DateTimeImmutable() > $this->ban->expiredAt ? $trans->trans('ban_expired', locale: $locale) : $trans->trans('ban_expires', locale: $locale),
                $intl->format($this->ban->expiredAt),
                $trans->trans('reason', locale: $locale),
                $this->ban->reason
            );
        } else {
            $message = \sprintf('%s %s: %s',
                $trans->trans('you_have_been_banned_from_magazine_permanently', ['%m' => $this->ban->magazine->name], locale: $locale),
                $trans->trans('reason', locale: $locale),
                $this->ban->reason
            );
        }
        $slash = $this->ban->magazine->icon && !str_starts_with('/', $this->ban->magazine->icon->filePath) ? '/' : '';
        $avatarUrl = $this->ban->magazine->icon ? '/media/cache/resolve/avatar_thumb'.$slash.$this->ban->magazine->icon->filePath : null;

        return new PushNotification($this->getId(), $message, $trans->trans('notification_title_ban', locale: $locale), avatarUrl: $avatarUrl);
    }
}
