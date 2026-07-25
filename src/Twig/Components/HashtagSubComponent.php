<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Domain;
use App\Entity\Hashtag;
use App\Entity\User;
use App\Service\TagManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsTwigComponent('hashtag_sub')]
final class HashtagSubComponent
{
    public Hashtag $hashtag;

    public bool $isHashtagBlocked;

    public function __construct(
        private readonly Security $security,
    ) {}

    #[PostMount]
    public function postMount(): void
    {
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $this->isHashtagBlocked = $user->isBlockedHashtag($this->hashtag);
        } else {
            $this->isHashtagBlocked = false;
        }
    }
}
