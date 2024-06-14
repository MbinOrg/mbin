<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping\Column;

trait ActivityPubActivityTrait
{
    #[Column(type: 'string', unique: true, nullable: true)]
    public ?string $apId = null;

    #[Column(type: 'integer', nullable: true)]
    public ?int $apLikeCount = null;

    #[Column(type: 'integer', nullable: true)]
    public ?int $apDislikeCount = null;

    #[Column(type: 'integer', nullable: true)]
    public ?int $apShareCount = null;
}
