<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

trait RankingTrait
{
    #[ORM\Column(type: 'integer')]
    public int $ranking = 0;

    public function updateRanking(): void
    {
        $score = $this->getScore();
        $scoreAdvantage = $score * self::NETSCORE_MULTIPLIER;

        if ($score > self::DOWNVOTED_CUTOFF) {
            $commentAdvantage = $this->getCommentCount() * self::COMMENT_MULTIPLIER;
            $commentAdvantage += $this->getUniqueCommentCount() * self::COMMENT_UNIQUE_MULTIPLIER;
        } else {
            $commentAdvantage = $this->getCommentCount() * self::COMMENT_DOWNVOTED_MULTIPLIER;
            $commentAdvantage += $this->getUniqueCommentCount() * self::COMMENT_DOWNVOTED_MULTIPLIER;
        }

        $advantage = max(min($scoreAdvantage + $commentAdvantage, self::MAX_ADVANTAGE), -self::MAX_PENALTY);

        // cap max date advantage at the time of calculation to cope with posts
        // that have funny dates (e.g. 4200-06-09)
        // which can cause int overflow (int32?) on ranking score
        $dateAdvantage = min($this->getCreatedAt()->getTimestamp(), (new \DateTimeImmutable())->getTimestamp());

        // also cap the final score to not exceed int32 size for the time being
        $this->ranking = min($dateAdvantage + $advantage, 2 ** 31 - 1);
    }

    public function getRanking(): int
    {
        return $this->ranking;
    }

    public function setRanking(int $ranking): void
    {
        $this->ranking = $ranking;
    }
}
