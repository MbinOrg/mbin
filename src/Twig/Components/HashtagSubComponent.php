<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Hashtag;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsTwigComponent('hashtag_sub')]
final class HashtagSubComponent
{
    public Hashtag $hashtag;

    public bool $isHashtagSubscribed;
    public bool $isHashtagBlocked;
    public int $hashtagSubscriptionCount;

    public function __construct(
        private readonly Security $security,
    ) {
    }

    #[PostMount]
    public function postMount(): void
    {
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $this->isHashtagSubscribed = $this->hashtag->isSubscribed($user);
            $this->isHashtagBlocked = $user->isBlockedHashtag($this->hashtag);
        } else {
            $this->isHashtagSubscribed = false;
            $this->isHashtagBlocked = false;
        }

        $this->hashtagSubscriptionCount = $this->hashtag->subscriptions->count();
    }
}
