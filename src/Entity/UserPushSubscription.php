<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToOne;

#[Entity]
class UserPushSubscription
{
    #[Id, GeneratedValue, Column(type: 'integer')]
    public int $id;

    #[ManyToOne(targetEntity: User::class, cascade: ['persist', 'remove'], inversedBy: 'pushSubscriptions')]
    public User $user;

    #[Column(type: 'text')]
    public string $endpoint;

    #[Column(type: 'text')]
    public string $contentEncryptionPublicKey;

    /**
     * @var OAuth2UserConsent|null this is only null for the web interface push messages
     */
    #[OneToOne(inversedBy: 'pushSubscription', targetEntity: OAuth2UserConsent::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[JoinColumn(name: 'user_consent', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    public ?OAuth2UserConsent $userConsent = null;

    #[Column(type: 'string', nullable: true)]
    public ?string $locale = null;

    #[Column(type: 'uuid', nullable: true)]
    public ?string $deviceKey = null;

    #[Column(type: 'text')]
    public string $serverAuthKey;

    /**
     * @var string[] the identifier of the notifications that this push subscription wants to receive
     *
     * @see Notification the discriminator map
     */
    #[Column(type: 'json')]
    public array $notificationTypes = [];

    /**
     * @param string[] $notifications
     */
    public function __construct(User $user, string $endpoint, string $contentEncryptionPublicKey, string $serverAuthKey, array $notifications, ?OAuth2UserConsent $userConsent = null)
    {
        $this->user = $user;
        $this->endpoint = $endpoint;
        $this->serverAuthKey = $serverAuthKey;
        $this->contentEncryptionPublicKey = $contentEncryptionPublicKey;
        $this->notificationTypes = $notifications;
        $this->userConsent = $userConsent;
    }
}
