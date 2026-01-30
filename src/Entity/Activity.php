<?php

declare(strict_types=1);

namespace App\Entity;

use App\Controller\ActivityPub\ObjectController;
use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Contracts\ActivityPubActorInterface;
use App\Entity\Traits\CreatedAtTrait;
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
    use CreatedAtTrait {
        CreatedAtTrait::__construct as createdAtTraitConstruct;
    }

    #[Column(type: 'uuid'), Id, GeneratedValue(strategy: 'CUSTOM')]
    #[CustomIdGenerator(class: 'doctrine.uuid_generator')]
    public Uuid $uuid;

    #[Column]
    public string $type;

    /**
     * If the activity is a remote activity then we will not return it through the @see ObjectController.
     */
    #[Column(nullable: false, options: ['default' => false])]
    public bool $isRemote = false;

    #[ManyToOne, JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?User $userActor;

    #[ManyToOne, JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Magazine $magazineActor;

    #[ManyToOne, JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Magazine $audience = null;

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

    #[ManyToOne, JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?MagazineBan $objectMagazineBan = null;

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
        $this->createdAtTraitConstruct();
    }

    public function setObject(ActivityPubActivityInterface|Entry|EntryComment|Post|PostComment|ActivityPubActorInterface|User|Magazine|MagazineBan|Activity|array|string $object): void
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
        } elseif ($object instanceof MagazineBan) {
            $this->objectMagazineBan = $object;
        } elseif ($object instanceof Activity) {
            $this->innerActivity = $object;
        } elseif (\is_array($object)) {
            if (isset($object['@context'])) {
                unset($object['@context']);
            }
            $this->objectGeneric = json_encode($object);
        } elseif (\is_string($object)) {
            $this->objectGeneric = $object;
        } else {
            throw new \LogicException(\get_class($object));
        }
    }

    public function getObject(): Post|EntryComment|PostComment|Entry|Message|User|Magazine|MagazineBan|array|string|null
    {
        $o = $this->objectEntry ?? $this->objectEntryComment ?? $this->objectPost ?? $this->objectPostComment ?? $this->objectMessage ?? $this->objectUser ?? $this->objectMagazine ?? $this->objectMagazineBan;
        if (null !== $o) {
            return $o;
        }
        $o = json_decode($this->objectGeneric ?? '', associative: true);
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
