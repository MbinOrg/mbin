<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enums\EPushNotificationType;
use App\Payloads\PushNotification;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
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

    public function getMessage(TranslatorInterface $trans, string $locale, UrlGeneratorInterface $urlGenerator): PushNotification
    {
        $message = \sprintf('%s %s: %s', $this->message->sender->username, $trans->trans('wrote_message'), $this->message->body);
        $slash = $this->message->sender->avatar && !str_starts_with('/', $this->message->sender->avatar->filePath) ? '/' : '';
        $avatarUrl = $this->message->sender->avatar ? '/media/cache/resolve/avatar_thumb'.$slash.$this->message->sender->avatar->filePath : null;
        $url = $urlGenerator->generate('messages_single', ['id' => $this->message->thread->getId()]);

        return new PushNotification($message, $trans->trans('notification_title_message', locale: $locale), actionUrl: $url, avatarUrl: $avatarUrl, category: EPushNotificationType::Message);
    }
}
