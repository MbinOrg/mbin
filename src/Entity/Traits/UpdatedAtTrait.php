<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

trait UpdatedAtTrait
{
    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    public ?\DateTimeImmutable $updatedAt = null;

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(): \DateTimeImmutable
    {
        $this->updatedAt = new \DateTimeImmutable('@'.time());

        return $this->updatedAt;
    }
}
