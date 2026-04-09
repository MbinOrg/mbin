<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enums\EPushNotificationType;
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
class MessageNotification extends Notification
{
    #[ManyToOne(targetEntity: Message::class, inversedBy: 'notifications')]
    #[JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Message $message = null;

    public function __construct(
        User $receiver,
        Message $message,
    ) {
        parent::__construct($receiver);

        $this->message = $message;
    }

    public function getSubject(): Message
    {
        return $this->message;
    }

    public function getType(): string
    {
        return 'message_notification';
    }

    public function getMessage(TranslatorInterface $trans, string $locale, ContainerInterface $serviceContainer): PushNotification
    {
        /** @var UserUrlFactory $userUrlFactory */
        $userUrlFactory = $serviceContainer->get(UserUrlFactory::class);
        /** @var UrlGeneratorInterface $urlGenerator */
        $urlGenerator = $serviceContainer->get(UrlGeneratorInterface::class);

        $message = \sprintf('%s %s: %s', $this->message->sender->username, $trans->trans('wrote_message'), $this->message->body);
        $avatarUrl = $userUrlFactory->getAvatarUrl($this->message->sender);
        $url = $urlGenerator->generate('messages_single', ['id' => $this->message->thread->getId()]);

        return new PushNotification($this->getId(), $message, $trans->trans('notification_title_message', locale: $locale), actionUrl: $url, avatarUrl: $avatarUrl, category: EPushNotificationType::Message);
    }
}
