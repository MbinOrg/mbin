<?php

declare(strict_types=1);

namespace App\DTO;

class RelatedLinkDTO
{
    private string $label;
    private string $value;
    private bool $verifiedLink = false;

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function isVerifiedLink(): bool
    {
        return $this->verifiedLink;
    }

    public function setVerifiedLink(bool $verifiedLink): void
    {
        $this->verifiedLink = $verifiedLink;
    }
}
