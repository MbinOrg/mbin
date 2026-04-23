<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity]
class MessageReport extends Report
{
    #[ManyToOne(targetEntity: Message::class, inversedBy: 'reports')]
    #[JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Message $message = null;

    public function __construct(User $reporting, Message $message, ?string $reason = null)
    {
        parent::__construct($reporting, $message->getUser(), null, $reason);

        $this->message = $message;
    }

    public function getSubject(): Message
    {
        return $this->message;
    }

    public function clearSubject(): Report
    {
        $this->message = null;

        return $this;
    }

    public function getType(): string
    {
        return 'message';
    }
}
