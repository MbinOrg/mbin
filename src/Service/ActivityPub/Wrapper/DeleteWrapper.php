<?php

declare(strict_types=1);

namespace App\Service\ActivityPub\Wrapper;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Factory\ActivityPub\ActivityFactory;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

class DeleteWrapper
{
    public function __construct(
        private readonly ActivityFactory $factory,
        private readonly AnnounceWrapper $announceWrapper,
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
    public function build(ActivityPubActivityInterface $item, string $id, User $deletingUser = null): array
    {
        $item = $this->factory->create($item);

        $userUrl = $item['attributedTo'];

        if (null !== $deletingUser) {
            // overwrite the actor in the json with the supplied deleting user
            if (null !== $deletingUser->apId) {
                $userUrl = $deletingUser->apPublicUrl;
            } else {
                $userUrl = $this->urlGenerator->generate('user_overview', ['username' => $deletingUser->username], UrlGeneratorInterface::ABSOLUTE_URL);
            }
        }

        return [
            '@context' => ActivityPubActivityInterface::CONTEXT_URL,
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Delete',
            'actor' => $userUrl,
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

    public function adjustDeletePayload(?User $actor, Entry|EntryComment|Post|PostComment $content, string $id): array
    {
        $payload = $this->build($content, $id, $actor);

        if (null !== $actor && $content->user->getId() !== $actor->getId()) {
            // if the user is different, then this is a mod action. Lemmy requires a mod action to have a summary
            $payload['summary'] = ' ';
        }

        if (null !== $actor?->apId) {
            // wrap the `Delete` in an `Announce` activity if the deleting user is not a local one
            $magazineUrl = $this->urlGenerator->generate('ap_magazine', ['name' => $content->magazine->name], UrlGeneratorInterface::ABSOLUTE_URL);
            $payload = $this->announceWrapper->build($magazineUrl, $payload);
        }

        return $payload;
    }
}
