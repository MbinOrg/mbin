<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

trait CreatedAtTrait
{
    public const NEW_FOR_DAYS = 30;

    #[ORM\Column(type: 'datetimetz_immutable')]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('@'.time());
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isNew(): bool
    {
        $days = self::NEW_FOR_DAYS;

        return $this->getCreatedAt() >= new \DateTime("now -$days days");
    }
}
