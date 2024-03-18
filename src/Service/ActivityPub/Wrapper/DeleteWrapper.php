<?php

declare(strict_types=1);

namespace App\Service\ActivityPub\Wrapper;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\User;
use App\Factory\ActivityPub\ActivityFactory;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

class DeleteWrapper
{
    public function __construct(
        private readonly ActivityFactory $factory,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    #[ArrayShape([
        '@context' => 'string',
        'id' => 'string',
        'type' => 'string',
        'object' => 'mixed',
        'actor' => 'mixed',
        'to' => 'mixed',
        'cc' => 'mixed',
    ])]
    public function build(ActivityPubActivityInterface $item, string $id): array
    {
        $item = $this->factory->create($item);

        return [
            '@context' => ActivityPubActivityInterface::CONTEXT_URL,
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Delete',
            'actor' => $item['attributedTo'],
            'object' => [
                'id' => $item['id'],
                'type' => 'Tombstone',
            ],
            'to' => $item['to'],
            'cc' => $item['cc'],
        ];
    }

    public function buildForUser(User $user): array
    {
        $id = Uuid::v4()->toRfc4122();
        $userId = $this->urlGenerator->generate('ap_user', ['username' => $user->username], UrlGeneratorInterface::ABSOLUTE_URL);

        return [
            '@context' => ActivityPubActivityInterface::CONTEXT_URL,
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Delete',
            'actor' => $userId,
            'object' => $userId,
            'to' => [ActivityPubActivityInterface::PUBLIC_URL],
            'cc' => [$this->urlGenerator->generate('ap_user_followers', ['username' => $user->username], UrlGeneratorInterface::ABSOLUTE_URL)],
            // this is a lemmy specific tag, that should cause the deletion of the data of a user (see this issue https://github.com/LemmyNet/lemmy/issues/4544)
            'removeData' => true,
        ];
    }
}
