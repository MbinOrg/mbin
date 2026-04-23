<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Contracts\ReportInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;

class ReportDto
{
    public ?Magazine $magazine = null;
    public ?User $reported = null;
    public ?ReportInterface $subject = null;
    public ?string $reason = null;
    private ?int $id = null;

    public static function create(ReportInterface $subject, ?string $reason = null, ?int $id = null): self
    {
        $dto = new ReportDto();
        $dto->id = $id;
        $dto->subject = $subject;
        $dto->reason = $reason;

        $dto->magazine = $subject->magazine ?? null;
        $dto->reported = $subject->getUser();

        return $dto;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRouteName(): string
    {
        return $this->getSubject()->getReportType();
    }

    public function getSubject(): ReportInterface
    {
        return $this->subject;
    }
}
