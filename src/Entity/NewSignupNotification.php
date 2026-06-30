<?php

declare(strict_types=1);

namespace App\Entity;

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
class NewSignupNotification extends Notification
{
    #[ManyToOne(targetEntity: User::class)]
    #[JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?User $newUser;

    public function getType(): string
    {
        return 'new_signup';
    }

    public function getSubject(): ?User
    {
        return $this->newUser;
    }

    public function getMessage(TranslatorInterface $trans, string $locale, ContainerInterface $serviceContainer): PushNotification
    {
        /** @var UserUrlFactory $userUrlFactory */
        $userUrlFactory = $serviceContainer->get(UserUrlFactory::class);

        $message = str_replace('%u%', $this->newUser->username, $trans->trans('notification_body_new_signup', locale: $locale));
        $title = $trans->trans('notification_title_new_signup', locale: $locale);
        $url = $userUrlFactory->getLocalUrl($this->newUser);
        $avatarUrl = $userUrlFactory->getAvatarUrl($this->newUser);

        return new PushNotification($this->getId(), $message, $title, actionUrl: $url, avatarUrl: $avatarUrl);
    }
}
