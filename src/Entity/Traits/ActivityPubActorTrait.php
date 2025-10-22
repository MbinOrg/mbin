<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use App\Service\ActivityPub\KeysGenerator;
use Doctrine\ORM\Mapping\Column;

trait ActivityPubActorTrait
{
    #[Column(type: 'string', unique: true, nullable: true)]
    public ?string $apId = null;

    #[Column(type: 'string', nullable: true)]
    public ?string $apProfileId = null;

    #[Column(type: 'string', nullable: true)]
    public ?string $apPublicUrl = null;

    #[Column(type: 'string', nullable: true)]
    public ?string $apFollowersUrl = null;

    #[Column(type: 'string', nullable: true)]
    public ?string $apAttributedToUrl = null;

    #[Column(type: 'string', nullable: true)]
    public ?string $apFeaturedUrl = null;

    #[Column(type: 'integer', nullable: true)]
    public ?int $apFollowersCount = null;

    #[Column(type: 'string', nullable: true)]
    public ?string $apInboxUrl = null;

    #[Column(type: 'string', nullable: true)]
    public ?string $apDomain = null;

    #[Column(type: 'string', nullable: true)]
    public ?string $apPreferredUsername = null;

    #[Column(type: 'boolean', nullable: true)]
    public ?bool $apDiscoverable = null;

    #[Column(type: 'boolean', nullable: true)]
    public ?bool $apManuallyApprovesFollowers = null;

    #[Column(type: 'text', nullable: true)]
    public ?string $privateKey = null;

    #[Column(type: 'text', nullable: true)]
    public ?string $publicKey = null;

    #[Column(type: 'text', nullable: true)]
    public ?string $oldPrivateKey = null;

    #[Column(type: 'text', nullable: true)]
    public ?string $oldPublicKey = null;

    #[Column(type: 'datetimetz', nullable: true)]
    public ?\DateTime $apFetchedAt = null;

    #[Column(type: 'datetimetz', nullable: true)]
    public ?\DateTime $apDeletedAt = null;

    #[Column(type: 'datetimetz', nullable: true)]
    public ?\DateTime $apTimeoutAt = null;

    #[Column(type: 'datetimetz', nullable: true)]
    public ?\DateTime $lastKeyRotationDate = null;

    public function getPrivateKey(): ?string
    {
        return $this->privateKey;
    }

    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }

    public function rotatePrivateKey(): void
    {
        $this->oldPrivateKey = $this->privateKey;
        $this->oldPublicKey = $this->publicKey;
        // set new private and public key
        KeysGenerator::generate($this);
        $this->lastKeyRotationDate = new \DateTime();
    }
}
