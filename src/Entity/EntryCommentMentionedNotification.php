<?php

declare(strict_types=1);

namespace App\Entity;

use App\Factory\Entry\EntryCommentUrlFactory;
use App\Factory\User\UserUrlFactory;
use App\Payloads\PushNotification;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\ManyToOne;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Entity]
class EntryCommentMentionedNotification extends Notification
{
    #[ManyToOne(targetEntity: EntryComment::class, inversedBy: 'notifications')]
    public ?EntryComment $entryComment;

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
        return 'entry_comment_mentioned_notification';
    }

    public function getMessage(TranslatorInterface $trans, string $locale, ContainerInterface $serviceContainer): PushNotification
    {
        /** @var EntryCommentUrlFactory $commentUrlFactory */
        $commentUrlFactory = $serviceContainer->get(EntryCommentUrlFactory::class);
        /** @var UserUrlFactory $userUrlFactory */
        $userUrlFactory = $serviceContainer->get(UserUrlFactory::class);

        $message = \sprintf('%s %s - %s', $this->entryComment->user->username, $trans->trans('mentioned_you'), $this->entryComment->getShortTitle());
        $avatarUrl = $userUrlFactory->getAvatarUrl($this->entryComment->user);
        $url = $commentUrlFactory->getLocalUrl($this->entryComment);

        return new PushNotification($this->getId(), $message, $trans->trans('notification_title_mention', locale: $locale), actionUrl: $url, avatarUrl: $avatarUrl);
    }
}
