<?php

namespace App\DTO;

class RelatedLinkDTO
{
    private string $label;
    private string $value;
    private bool $verifiedLink = false;

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @param string $label
     */
    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @param string $value
     */
    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    /**
     * @return bool
     */
    public function isVerifiedLink(): bool
    {
        return $this->verifiedLink;
    }

    /**
     * @param bool $verifiedLink
     */
    public function setVerifiedLink(bool $verifiedLink): void
    {
        $this->verifiedLink = $verifiedLink;
    }
}
