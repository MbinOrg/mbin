<?php

declare(strict_types=1);

namespace App\Entity;

use App\Factory\Entry\EntryUrlFactory;
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
class EntryDeletedNotification extends Notification
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
        return 'entry_deleted_notification';
    }

    public function getMessage(TranslatorInterface $trans, string $locale, ContainerInterface $serviceContainer): PushNotification
    {
        /** @var EntryUrlFactory $entryUrlFactory */
        $entryUrlFactory = $serviceContainer->get(EntryUrlFactory::class);
        /** @var UserUrlFactory $userUrlFactory */
        $userUrlFactory = $serviceContainer->get(UserUrlFactory::class);

        $message = \sprintf('%s %s', $this->entry->getShortTitle(), $this->entry->isTrashed() ? $trans->trans('removed') : $trans->trans('deleted'));
        $avatarUrl = $userUrlFactory->getAvatarUrl($this->entry->user);
        $url = $entryUrlFactory->getLocalUrl($this->entry);

        return new PushNotification($this->getId(), $message, $trans->trans('notification_title_removed_thread', locale: $locale), actionUrl: $url, avatarUrl: $avatarUrl);
    }
}
