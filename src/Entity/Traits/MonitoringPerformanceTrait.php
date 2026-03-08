<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping\Column;

trait MonitoringPerformanceTrait
{
    #[Column]
    private \DateTimeImmutable $startedAt;

    #[Column]
    private float $startedAtMicroseconds;

    #[Column]
    private \DateTimeImmutable $endedAt;

    #[Column]
    private float $endedAtMicroseconds;

    #[Column]
    private float $durationMilliseconds;

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(): void
    {
        $this->startedAt = new \DateTimeImmutable();
        $this->startedAtMicroseconds = microtime(true);
    }

    public function setEndedAt(): void
    {
        $this->endedAt = new \DateTimeImmutable();
        $this->endedAtMicroseconds = microtime(true);
    }

    public function getEndedAt(): \DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setDuration(): void
    {
        $this->durationMilliseconds = ($this->endedAtMicroseconds - $this->startedAtMicroseconds) * 1000;
    }

    public function getDuration(): float
    {
        return $this->durationMilliseconds;
    }
}
