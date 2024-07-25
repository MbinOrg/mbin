<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Repository\InstanceRepository;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;

#[Entity(repositoryClass: InstanceRepository::class)]
class Instance
{
    use CreatedAtTrait {
        CreatedAtTrait::__construct as createdAtTraitConstruct;
    }
    use UpdatedAtTrait;
    public const NUMBER_OF_FAILED_DELIVERS_UNTIL_DEAD = 10;

    public static function getDateBeforeDead(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now - 7 days');
    }

    #[Column(nullable: true)]
    public ?string $software;

    #[Column(nullable: true)]
    public ?string $version;

    #[Column]
    public string $domain;

    #[Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSuccessfulDeliver;

    #[Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastFailedDeliver;

    #[Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSuccessfulReceive;

    #[Column]
    private int $failedDelivers = 0;

    #[Column, Id, GeneratedValue]
    private int $id;

    public function __construct(string $domain)
    {
        $this->domain = $domain;
        $this->createdAtTraitConstruct();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setLastSuccessfulDeliver(): void
    {
        $this->lastSuccessfulDeliver = new \DateTimeImmutable();
        $this->failedDelivers = 0;
    }

    public function getLastSuccessfulDeliver(): ?\DateTimeImmutable
    {
        return $this->lastSuccessfulDeliver;
    }

    public function setLastFailedDeliver(): void
    {
        $this->lastFailedDeliver = new \DateTimeImmutable();
        ++$this->failedDelivers;
    }

    public function getLastFailedDeliver(): ?\DateTimeImmutable
    {
        return $this->lastFailedDeliver;
    }

    public function setLastSuccessfulReceive(): void
    {
        $this->lastSuccessfulReceive = new \DateTimeImmutable();
    }

    public function getLastSuccessfulReceive(): ?\DateTimeImmutable
    {
        return $this->lastSuccessfulReceive;
    }

    public function getFailedDelivers(): int
    {
        return $this->failedDelivers;
    }

    public function isDead(): bool
    {
        return $this->getLastSuccessfulDeliver() < self::getDateBeforeDead() && $this->getFailedDelivers() >= self::NUMBER_OF_FAILED_DELIVERS_UNTIL_DEAD;
    }
}
