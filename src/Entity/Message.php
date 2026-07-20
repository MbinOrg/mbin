<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Contracts\ReportInterface;
use App\Entity\Traits\ActivityPubActivityTrait;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\EditedAtTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Symfony\Component\Uid\Uuid;

#[Entity]
class Message implements ActivityPubActivityInterface, ReportInterface
{
    use ActivityPubActivityTrait;
    use CreatedAtTrait {
        CreatedAtTrait::__construct as createdAtTraitConstruct;
    }
    use EditedAtTrait;

    public const STATUS_NEW = 'new';
    public const STATUS_READ = 'read';
    public const STATUS_OPTIONS = [
        self::STATUS_NEW,
        self::STATUS_READ,
    ];

    public const string REPORT_TYPE = 'message_report';

    #[ManyToOne(targetEntity: MessageThread::class, inversedBy: 'messages')]
    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public MessageThread $thread;
    #[ManyToOne(targetEntity: User::class)]
    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $sender;
    #[Column(type: 'text', nullable: false)]
    public string $body;
    #[Column(type: 'string', nullable: false)]
    public string $status = self::STATUS_NEW;
    #[Column(type: 'uuid', unique: true, nullable: false)]
    public string $uuid;
    #[OneToMany(mappedBy: 'message', targetEntity: MessageReport::class, cascade: ['remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    public Collection $reports;
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    private int $id;
    #[OneToMany(mappedBy: 'message', targetEntity: MessageNotification::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $notifications;

    public function __construct(MessageThread $thread, User $sender, string $body, ?string $apId)
    {
        $this->thread = $thread;
        $this->sender = $sender;
        $this->body = $body;
        $this->notifications = new ArrayCollection();
        $this->uuid = Uuid::v4()->toRfc4122();
        $this->apId = $apId;
        $this->reports = new ArrayCollection();

        $thread->addMessage($this);

        $this->createdAtTraitConstruct();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        $firstLine = preg_replace('/^# |\R.*/', '', $this->body);

        if (grapheme_strlen($firstLine) <= 80) {
            return $firstLine;
        }

        return grapheme_substr($firstLine, 0, 80).'…';
    }

    public function getUser(): User
    {
        return $this->sender;
    }


    public function getApId(): ?string
    {
        return $this->apId;
    }

    public function getMagazine(): ?Magazine
    {
        return null;
    }

    public function getShortTitle(): string {
        $body = wordwrap($this->body, 60);
        $body = explode("\n", $body);
        return trim($body[0]).(isset($body[1]) ? '...' : '');
    }

    public function getReportType(): string
    {
        return self::REPORT_TYPE;
    }
}
