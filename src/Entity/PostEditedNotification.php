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
class PostEditedNotification extends Notification
{
    #[ManyToOne(targetEntity: Post::class, inversedBy: 'notifications')]
    #[JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Post $post = null;

    public function __construct(User $receiver, Post $post)
    {
        parent::__construct($receiver);

        $this->post = $post;
    }

    public function getSubject(): Post
    {
        return $this->post;
    }

    public function getType(): string
    {
        return 'post_edited_notification';
    }

    public function getMessage(TranslatorInterface $trans, string $locale, UrlGeneratorInterface $urlGenerator): PushNotification
    {
        $message = \sprintf('%s %s - %s', $this->post->user->username, $trans->trans('edited_post'), $this->post->getShortTitle());
        $slash = $this->post->user->avatar && !str_starts_with('/', $this->post->user->avatar->filePath) ? '/' : '';
        $avatarUrl = $this->post->user->avatar ? '/media/cache/resolve/avatar_thumb'.$slash.$this->post->user->avatar->filePath : null;
        $url = $urlGenerator->generate('post_single', [
            'magazine_name' => $this->post->magazine->name,
            'post_id' => $this->post->getId(),
            'slug' => empty($this->postComment->post->slug) ? '-' : $this->postComment->post->slug,
        ]);

        return new PushNotification($this->getId(), $message, $trans->trans('notification_title_edited_post', locale: $locale), actionUrl: $url, avatarUrl: $avatarUrl);
    }
}
