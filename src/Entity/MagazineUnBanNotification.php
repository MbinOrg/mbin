<?php

declare(strict_types=1);

namespace App\Entity;

use App\Factory\Magazine\MagazineUrlFactory;
use App\Factory\User\UserUrlFactory;
use App\Payloads\PushNotification;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
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

    public function getMessage(TranslatorInterface $trans, string $locale, ContainerInterface $serviceContainer): PushNotification
    {
        /** @var MagazineUrlFactory $magazineUrlFactory */
        $magazineUrlFactory = $serviceContainer->get(MagazineUrlFactory::class);

        $message = $trans->trans('you_are_no_longer_banned_from_magazine', ['%m' => $this->ban->magazine->name], locale: $locale);
        $avatarUrl = $magazineUrlFactory->getAvatarUrl($this->ban->magazine);

        return new PushNotification($this->getId(), $message, $trans->trans('notification_title_ban', locale: $locale), avatarUrl: $avatarUrl);
    }
}
