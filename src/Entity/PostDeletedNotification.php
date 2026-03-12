<?php

declare(strict_types=1);

namespace App\Entity;

use App\Factory\Post\PostUrlFactory;
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
class PostDeletedNotification extends Notification
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
        return 'post_deleted_notification';
    }

    public function getMessage(TranslatorInterface $trans, string $locale, ContainerInterface $serviceContainer): PushNotification
    {
        /** @var PostUrlFactory $postUrlFactory */
        $postUrlFactory = $serviceContainer->get(PostUrlFactory::class);
        /** @var UserUrlFactory $userUrlFactory */
        $userUrlFactory = $serviceContainer->get(UserUrlFactory::class);

        $message = \sprintf('%s %s - %s', $trans->trans('post'), $this->post->getShortTitle(), $this->post->isTrashed() ? $trans->trans('removed') : $trans->trans('deleted'));
        $avatarUrl = $userUrlFactory->getAvatarUrl($this->post->user);
        $url = $postUrlFactory->getLocalUrl($this->post);

        return new PushNotification($this->getId(), $message, $trans->trans('notification_title_removed_post', locale: $locale), actionUrl: $url, avatarUrl: $avatarUrl);
    }
}
