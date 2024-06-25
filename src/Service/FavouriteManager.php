<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contracts\FavouriteInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Favourite;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Event\FavouriteEvent;
use App\Factory\FavouriteFactory;
use App\Repository\FavouriteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class FavouriteManager
{
    public const TYPE_LIKE = 'like';
    public const TYPE_UNLIKE = 'unlike';

    public function __construct(
        private readonly FavouriteFactory $factory,
        private readonly FavouriteRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public function toggle(User $user, FavouriteInterface $subject, string $type = null): ?Favourite
    {
        if (!($favourite = $this->repository->findBySubject($user, $subject))) {
            if (self::TYPE_UNLIKE === $type) {
                return null;
            }

            $favourite = $this->factory->createFromEntity($user, $subject);
            $this->entityManager->persist($favourite);

            $subject->favourites->add($favourite);
            $subject->updateCounts();
            $subject->updateScore();
            $subject->updateRanking();

            if ($subject instanceof Entry || $subject instanceof EntryComment || $subject instanceof Post || $subject instanceof PostComment) {
                if (null !== $subject->apLikeCount) {
                    ++$subject->apLikeCount;
                }
            }
        } else {
            if (self::TYPE_LIKE === $type) {
                if ($subject instanceof Entry || $subject instanceof EntryComment || $subject instanceof Post || $subject instanceof PostComment) {
                    if (null !== $subject->apLikeCount) {
                        ++$subject->apLikeCount;
                    }
                }

                return $favourite;
            }

            $subject->favourites->removeElement($favourite);
            $subject->updateCounts();
            $subject->updateScore();
            $subject->updateRanking();
            $favourite = null;
            if ($subject instanceof Entry || $subject instanceof EntryComment || $subject instanceof Post || $subject instanceof PostComment) {
                if (null !== $subject->apLikeCount) {
                    --$subject->apLikeCount;
                }
            }
        }

        $this->entityManager->flush();

        $this->dispatcher->dispatch(new FavouriteEvent($subject, $user, null === $favourite));

        return $favourite ?? null;
    }
}
