<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enums\ENotificationStatus;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\UniqueConstraint;

#[Entity]
#[UniqueConstraint(name: 'notification_settings_user_target', columns: ['user_id', 'entry_id', 'post_id', 'magazine_id', 'target_user_id'])]
class NotificationSettings
{
    #[Id, GeneratedValue, Column(type: 'integer')]
    private int $id;

    #[ManyToOne(targetEntity: User::class, cascade: ['remove'])]
    #[JoinColumn(nullable: false)]
    public User $user;

    #[ManyToOne(targetEntity: Entry::class, cascade: ['remove'])]
    #[JoinColumn(nullable: true)]
    public ?Entry $entry = null;

    #[ManyToOne(targetEntity: Post::class, cascade: ['remove'])]
    #[JoinColumn(nullable: true)]
    public ?Post $post = null;

    #[ManyToOne(targetEntity: Magazine::class, cascade: ['remove'])]
    #[JoinColumn(nullable: true)]
    public ?Magazine $magazine = null;

    #[ManyToOne(targetEntity: User::class, cascade: ['remove'])]
    #[JoinColumn(nullable: true)]
    public ?User $targetUser = null;

    #[Column(type: 'enumNotificationStatus', nullable: false, options: ['default' => ENotificationStatus::Default->value])]
    private string $notificationStatus = ENotificationStatus::Default->value;

    public function __construct(User $user, Entry|Post|User|Magazine $target, ENotificationStatus $status)
    {
        $this->user = $user;
        $this->setStatus($status);
        if ($target instanceof User) {
            $this->targetUser = $target;
        } elseif ($target instanceof Magazine) {
            $this->magazine = $target;
        } elseif ($target instanceof Entry) {
            $this->entry = $target;
        } elseif ($target instanceof Post) {
            $this->post = $target;
        }
    }

    public function setStatus(ENotificationStatus $status): void
    {
        $this->notificationStatus = $status->value;
    }

    public function getStatus(): ENotificationStatus
    {
        return ENotificationStatus::getFromString($this->notificationStatus);
    }
}
