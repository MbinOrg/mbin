<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity]
class MagazineBanNotification extends Notification
{
    #[ManyToOne(targetEntity: MagazineBan::class)]
    #[JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?MagazineBan $ban = null;

    public function __construct(User $receiver, MagazineBan $ban)
    {
        parent::__construct($receiver);

        $this->ban = $ban;
    }

    public function getSubject(): MagazineBan
    {
        return $this->ban;
    }

    public function getType(): string
    {
        return 'magazine_ban_notification';
    }
}
