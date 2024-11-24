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
class NewSignupNotification extends Notification
{
    #[ManyToOne(targetEntity: User::class, cascade: ['remove'])]
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

    public function getMessage(TranslatorInterface $trans, string $locale, UrlGeneratorInterface $urlGenerator): PushNotification
    {
        $message = str_replace('%u%', $this->newUser->username, $trans->trans('notification_body_new_signup', locale: $locale));
        $title = $trans->trans('notification_title_new_signup', locale: $locale);
        $url = $urlGenerator->generate('user_overview', ['username' => $this->newUser->username]);
        $slash = $this->newUser->avatar && !str_starts_with('/', $this->newUser->avatar->filePath) ? '/' : '';
        $avatarUrl = $this->newUser->avatar ? '/media/cache/resolve/avatar_thumb'.$slash.$this->newUser->avatar->filePath : null;

        return new PushNotification($message, $title, actionUrl: $url, avatarUrl: $avatarUrl);
    }
}
