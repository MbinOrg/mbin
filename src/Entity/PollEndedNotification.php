<?php

declare(strict_types=1);

namespace App\Entity;

use App\Payloads\PushNotification;
use App\Repository\ApActivityRepository;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Entity]
class PollEndedNotification extends Notification
{
    #[JoinColumn(onDelete: 'CASCADE')]
    #[ManyToOne(targetEntity: Poll::class)]
    public Poll $poll;

    public function __construct(User $receiver, Poll $poll)
    {
        parent::__construct($receiver);
        $this->poll = $poll;
    }

    public function getType(): string
    {
        return 'poll_ended';
    }

    public function getMessage(TranslatorInterface $trans, string $locale, UrlGeneratorInterface $urlGenerator): PushNotification
    {
        $content = $this->poll->entry ?? $this->poll->entryComment ?? $this->poll->post ?? $this->poll->postComment;
        $message = '';
        $title = $trans->trans('notification_title_poll_ended', locale: $locale);
        $action = ApActivityRepository::s_getLocalUrlOfEntity($urlGenerator, $content, true);

        return new PushNotification($this->getId(), $message, $title, $action);
    }
}
