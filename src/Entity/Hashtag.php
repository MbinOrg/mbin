<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;

#[Entity(repositoryClass: TagRepository::class)]
class Hashtag
{
    #[Id, GeneratedValue]
    #[Column(type: 'integer')]
    private int $id;

    #[Column(type: 'citext', unique: true)]
    public string $tag;

    #[Column(type: 'boolean', options: ['default' => false])]
    public bool $banned = false;

    #[OneToMany(mappedBy: 'hashtag', targetEntity: HashtagSubscription::class, fetch: 'EXTRA_LAZY', cascade: [
        'persist',
        'remove',
    ], orphanRemoval: true)]
    public Collection $subscriptions;

    #[OneToMany(mappedBy: 'hashtag', targetEntity: HashtagLink::class, fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    public Collection $linkedPosts;

    public function __construct()
    {
        $this->subscriptions = new ArrayCollection();
        $this->linkedPosts = new ArrayCollection();
    }

    public function subscribe(User $user): void
    {
        if (!$this->isSubscribed($user)) {
            $subscription = new HashtagSubscription($user, $this);
            $this->subscriptions->add($subscription);
            $user->subscribedHashtags->add($subscription);
        }
    }

    public function unsubscribe(User $user): void
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('user', $user));

        /** @var HashtagSubscription $subscription */
        $subscription = $this->subscriptions->matching($criteria)->first();

        if ($this->subscriptions->removeElement($subscription)) {
            if ($subscription->hashtag === $this) {
                $subscription->hashtag = null;
            }
            $user->subscribedHashtags->removeElement($subscription);
        }
    }

    public function isSubscribed(User $user): bool
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('user', $user));

        return !$this->subscriptions->matching($criteria)->isEmpty();
    }
}
