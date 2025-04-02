<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Service\VoteManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class VoteFixtures extends BaseFixture implements DependentFixtureInterface
{
    public function __construct(private readonly VoteManager $voteManager)
    {
    }

    public function loadData(ObjectManager $manager): void
    {
        for ($u = 0; $u <= UserFixtures::USERS_COUNT; ++$u) {
            $this->entries($u);
            $this->entryComments($u);
            $this->posts($u);
            $this->postComments($u);
        }
    }

    private function entries(int $u): void
    {
        $randomNb = $this->getUniqueNb(
            EntryFixtures::ENTRIES_COUNT,
            rand(0, 155),
        );

        foreach ($randomNb as $e) {
            $roll = rand(0, 2);

            if (0 === $roll) {
                continue;
            }

            $this->voteManager->vote(
                rand(0, 4) > 0 ? 1 : -1,
                $this->getReference('entry_'.$e, Entry::class),
                $this->getReference('user_'.$u, User::class)
            );
        }
    }

    /**
     * @return int[]
     */
    private function getUniqueNb(int $max, int $quantity): array
    {
        $numbers = range(1, $max);
        shuffle($numbers);

        return \array_slice($numbers, 0, $quantity);
    }

    private function entryComments(int $u): void
    {
        $randomNb = $this->getUniqueNb(
            EntryCommentFixtures::COMMENTS_COUNT,
            rand(0, 155),
        );

        foreach ($randomNb as $c) {
            $roll = rand(0, 2);

            if (0 === $roll) {
                continue;
            }

            $this->voteManager->vote(
                rand(0, 4) > 0 ? 1 : -1,
                $this->getReference('entry_comment_'.$c, EntryComment::class),
                $this->getReference('user_'.$u, User::class)
            );
        }
    }

    private function posts(int $u): void
    {
        $randomNb = $this->getUniqueNb(
            PostFixtures::ENTRIES_COUNT,
            rand(0, 155),
        );

        foreach ($randomNb as $e) {
            $roll = rand(0, 2);

            if (0 === $roll) {
                continue;
            }

            $this->voteManager->vote(
                rand(0, 4) > 0 ? 1 : -1,
                $this->getReference('post_'.$e, Post::class),
                $this->getReference('user_'.$u, User::class)
            );
        }
    }

    private function postComments(int $u): void
    {
        $randomNb = $this->getUniqueNb(
            PostCommentFixtures::COMMENTS_COUNT,
            rand(0, 155),
        );

        foreach ($randomNb as $c) {
            $roll = rand(0, 2);

            if (0 === $roll) {
                continue;
            }

            $this->voteManager->vote(
                rand(0, 4) > 0 ? 1 : -1,
                $this->getReference('post_comment_'.$c, PostComment::class),
                $this->getReference('user_'.$u, User::class)
            );
        }
    }

    public function getDependencies(): array
    {
        return [
            EntryFixtures::class,
            EntryCommentFixtures::class,
            PostFixtures::class,
            PostCommentFixtures::class,
        ];
    }
}
