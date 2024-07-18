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
class PostCommentEditedNotification extends Notification
{
    #[ManyToOne(targetEntity: PostComment::class, inversedBy: 'notifications')]
    #[JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?PostComment $postComment = null;

    public function __construct(User $receiver, PostComment $comment)
    {
        parent::__construct($receiver);

        $this->postComment = $comment;
    }

    public function getSubject(): PostComment
    {
        return $this->postComment;
    }

    public function getComment(): PostComment
    {
        return $this->postComment;
    }

    public function getType(): string
    {
        return 'post_comment_edited_notification';
    }

    public function getMessage(TranslatorInterface $trans, string $locale, UrlGeneratorInterface $urlGenerator): PushNotification
    {
        $message = sprintf('%s %s - %s', $this->postComment->user->username, $trans->trans('edited_comment'), $this->postComment->getShortTitle());
        $slash = $this->postComment->user->avatar && !str_starts_with('/', $this->postComment->user->avatar->filePath) ? '/' : '';
        $avatarUrl = $this->postComment->user->avatar ? '/media/cache/resolve/avatar_thumb'.$slash.$this->postComment->user->avatar->filePath : null;
        $url = $urlGenerator->generate('post_single', [
            'magazine_name' => $this->postComment->post->magazine->name,
            'post_id' => $this->postComment->post->getId(),
            'slug' => empty($this->postComment->post->slug) ? '-' : $this->postComment->post->slug,
        ]).'#post-comment-'.$this->postComment->getId();

        return new PushNotification($message, $trans->trans('notification_title_edited_comment', locale: $locale), actionUrl: $url, avatarUrl: $avatarUrl);
    }
}
