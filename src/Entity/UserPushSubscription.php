<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use League\Bundle\OAuth2ServerBundle\Model\AccessToken;

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
     * @var AccessToken|null this is only null for the web interface push messages
     */
    #[ManyToOne(targetEntity: AccessToken::class, cascade: ['persist', 'remove'])]
    #[JoinColumn(name: 'api_token', referencedColumnName: 'identifier', unique: true, nullable: true, onDelete: 'CASCADE')]
    public ?AccessToken $apiToken = null;

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
    public function __construct(User $user, string $endpoint, string $contentEncryptionPublicKey, string $serverAuthKey, array $notifications, ?AccessToken $apiToken = null)
    {
        $this->user = $user;
        $this->endpoint = $endpoint;
        $this->serverAuthKey = $serverAuthKey;
        $this->contentEncryptionPublicKey = $contentEncryptionPublicKey;
        $this->notificationTypes = $notifications;
        $this->apiToken = $apiToken;
    }
}
