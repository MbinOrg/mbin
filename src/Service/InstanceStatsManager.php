<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\MagazineRepository;
use App\Repository\StatsContentRepository;
use App\Repository\UserRepository;
use App\Repository\VoteRepository;
use Doctrine\Common\Collections\Criteria;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class InstanceStatsManager
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly StatsContentRepository $statsContentRepository,
        private readonly VoteRepository $voteRepository,
        private readonly CacheInterface $cache
    ) {
    }

    public function count(?string $period = null, bool $withFederated = false)
    {
        $periodDate = $period ? \DateTimeImmutable::createFromMutable(new \DateTime($period)) : null;

        return $this->cache->get('instance_stats', function (ItemInterface $item) use ($periodDate, $withFederated) {
            $item->expiresAfter(0);

            $criteria = Criteria::create();

            if ($periodDate) {
                $criteria->where(
                    Criteria::expr()
                        ->gt('createdAt', $periodDate)
                );
            }

            if (!$withFederated) {
                if ($periodDate) {
                    $criteria->andWhere(
                        Criteria::expr()->eq('apId', null)
                    );
                } else {
                    $criteria->where(
                        Criteria::expr()->eq('apId', null)
                    );
                }
            }

            $userCriteria = clone $criteria;
            $userCriteria->andWhere(Criteria::expr()->eq('isDeleted', false));

            return [
                'users' => $this->userRepository->matching($userCriteria)->count(),
                'magazines' => $this->magazineRepository->matching($criteria)->count(),
                'entries' => $this->statsContentRepository->aggregateStats('entry', $periodDate, null, $withFederated, null),
                'comments' => $this->statsContentRepository->aggregateStats('entry_comment', $periodDate, null, $withFederated, null),
                'posts' => $this->statsContentRepository->aggregateStats('post', $periodDate, null, $withFederated, null) +
                    $this->statsContentRepository->aggregateStats('post_comment', $periodDate, null, $withFederated, null),
                'votes' => $this->voteRepository->count($periodDate, $withFederated),
            ];
        });
    }
}
