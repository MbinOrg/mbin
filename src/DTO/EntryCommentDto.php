<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Image;
use App\Entity\Magazine;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class EntryCommentDto
{
    public Magazine|MagazineDto|null $magazine = null;
    public User|UserDto|null $user = null;
    public Entry|EntryDto|null $entry = null;
    public ?EntryComment $parent = null;
    public ?EntryComment $root = null;
    public Image|ImageDto|null $image = null;
    public ?string $imageUrl = null;
    public ?string $imageAlt = null;
    #[Assert\Length(max: 5000)]
    public ?string $body = null;
    public ?string $lang = null;
    public bool $isAdult = false;
    public ?int $uv = null;
    public ?int $dv = null;
    public ?string $visibility = VisibilityInterface::VISIBILITY_VISIBLE;
    public ?string $ip = null;
    public ?string $apId = null;
    public ?array $mentions = null;
    public ?\DateTimeImmutable $createdAt = null;
    public ?\DateTime $lastActive = null;
    private ?int $id = null;

    #[Assert\Callback]
    public function validate(
        ExecutionContextInterface $context,
        $payload
    ) {
        $image = Request::createFromGlobals()->files->filter('entry_comment');

        if (is_array($image) && isset($image['image'])) {
            $image = $image['image'];
        } else {
            $image = $context->getValue()->image;
        }

        if (empty($this->body) && empty($image)) {
            $this->buildViolation($context, 'body');
        }
    }

    private function buildViolation(ExecutionContextInterface $context, $path)
    {
        $context->buildViolation('This value should not be blank.')
            ->atPath($path)
            ->addViolation();
    }

    public function createWithParent(
        Entry $entry,
        ?EntryComment $parent,
        ?Image $image = null,
        ?string $body = null
    ): self {
        $this->entry = $entry;
        $this->parent = $parent;
        $this->body = $body;
        $this->image = $image;

        if ($parent) {
            $this->root = $parent->root ?? $parent;
        }

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }
}
