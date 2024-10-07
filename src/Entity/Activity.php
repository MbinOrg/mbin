<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Contracts\ActivityPubActorInterface;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\CustomIdGenerator;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Symfony\Component\Uid\Uuid;

#[Entity]
class Activity
{
    #[Column(type: 'uuid'), Id, GeneratedValue(strategy: 'CUSTOM')]
    #[CustomIdGenerator(class: 'doctrine.uuid_generator')]
    public Uuid $uuid;

    #[Column]
    public string $type;

    #[ManyToOne, JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?User $userActor;

    #[ManyToOne, JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Magazine $magazineActor;

    #[ManyToOne, JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Magazine $audience;

    #[ManyToOne, JoinColumn(referencedColumnName: 'uuid', nullable: true, onDelete: 'CASCADE', options: ['default' => null])]
    public ?Activity $innerActivity = null;

    #[Column(type: 'text', nullable: true)]
    public ?string $innerActivityUrl = null;

    #[ManyToOne, JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Entry $objectEntry = null;

    #[ManyToOne, JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?EntryComment $objectEntryComment = null;

    #[ManyToOne, JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Post $objectPost = null;

    #[ManyToOne, JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?PostComment $objectPostComment = null;

    #[ManyToOne, JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Message $objectMessage = null;

    #[ManyToOne, JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?User $objectUser = null;

    #[ManyToOne, JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Magazine $objectMagazine = null;

    #[Column(type: 'text', nullable: true)]
    public ?string $objectGeneric = null;

    #[Column(type: 'text', nullable: true)]
    public ?string $targetString = null;

    #[Column(type: 'text', nullable: true)]
    public ?string $contentString = null;

    /**
     * This should only be set when the json should not get compiled.
     */
    #[Column(type: 'text', nullable: true)]
    public ?string $activityJson = null;

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    public function setObject(ActivityPubActivityInterface|Entry|EntryComment|Post|PostComment|ActivityPubActorInterface|User|Magazine|array|string $object): void
    {
        if ($object instanceof Entry) {
            $this->objectEntry = $object;
        } elseif ($object instanceof EntryComment) {
            $this->objectEntryComment = $object;
        } elseif ($object instanceof Post) {
            $this->objectPost = $object;
        } elseif ($object instanceof PostComment) {
            $this->objectPostComment = $object;
        } elseif ($object instanceof Message) {
            $this->objectMessage = $object;
        } elseif ($object instanceof User) {
            $this->objectUser = $object;
        } elseif ($object instanceof Magazine) {
            $this->objectMagazine = $object;
        } elseif (\is_array($object)) {
            $this->objectGeneric = json_encode($object);
        } elseif (\is_string($object)) {
            $this->objectGeneric = $object;
        } else {
            throw new \LogicException(\get_class($object));
        }
    }

    public function getObject(): Post|EntryComment|PostComment|Entry|Message|User|Magazine|array|string|null
    {
        $o = $this->objectEntry ?? $this->objectEntryComment ?? $this->objectPost ?? $this->objectPostComment ?? $this->objectMessage ?? $this->objectUser ?? $this->objectMagazine;
        if (null !== $o) {
            return $o;
        }
        $o = json_decode($this->objectGeneric ?? '');
        if (JSON_ERROR_NONE === json_last_error()) {
            return $o;
        }

        return $this->objectGeneric;
    }

    public function setActor(Magazine|User $actor): void
    {
        if ($actor instanceof User) {
            $this->userActor = $actor;
        } else {
            $this->magazineActor = $actor;
        }
    }

    public function getActor(): Magazine|User|null
    {
        return $this->userActor ?? $this->magazineActor;
    }
}
