<?php

declare(strict_types=1);

namespace App\Entity;

use App\DTO\GroupedMonitoringQueryDto;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\MonitoringPerformanceTrait;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\CustomIdGenerator;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;
use Symfony\Component\Uid\Uuid;

#[Entity]
class MonitoringExecutionContext
{
    use CreatedAtTrait {
        CreatedAtTrait::__construct as createdAtTraitConstruct;
    }
    use MonitoringPerformanceTrait;

    #[Column(type: 'uuid'), Id, GeneratedValue(strategy: 'CUSTOM')]
    #[CustomIdGenerator(class: 'doctrine.uuid_generator')]
    public Uuid $uuid;

    /**
     * @var string 'request'|'messenger'
     */
    #[Column]
    public string $executionType;

    /**
     * @var string the path or the message class
     */
    #[Column]
    public string $path;

    /**
     * @var string the controller or the message transport
     */
    #[Column]
    public string $handler;

    /**
     * @var string 'anonymous'|'user'|'activity_pub'|'ajax'
     */
    #[Column]
    public string $userType;

    #[Column(nullable: true)]
    public ?int $statusCode = null;

    #[Column(nullable: true)]
    public ?string $exception = null;

    #[Column(nullable: true)]
    public ?string $stacktrace = null;

    #[Column]
    public float $queryDurationMilliseconds;

    #[Column]
    public float $twigRenderDurationMilliseconds;

    #[Column]
    public float $curlRequestDurationMilliseconds;

    /**
     * @var Collection<MonitoringQuery>
     */
    #[OneToMany(mappedBy: 'context', targetEntity: MonitoringQuery::class, fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    public Collection $queries;

    /**
     * @var Collection<MonitoringCurlRequest>
     */
    #[OneToMany(mappedBy: 'context', targetEntity: MonitoringCurlRequest::class, fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    public Collection $curlRequests;

    /**
     * @var Collection<MonitoringTwigRender>
     */
    #[OneToMany(mappedBy: 'context', targetEntity: MonitoringTwigRender::class, fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    public Collection $twigRenders;

    public function __construct()
    {
        $this->createdAtTraitConstruct();
    }

    /**
     * @return Collection<MonitoringQuery>
     */
    public function getQueriesSorted(string $sortBy = 'durationMilliseconds', string $sortDirection = 'DESC'): Collection
    {
        $criteria = new Criteria(orderings: [$sortBy => $sortDirection]);

        return $this->queries->matching($criteria);
    }

    /**
     * @return GroupedMonitoringQueryDto[]
     */
    public function getGroupedQueries(): array
    {
        /** @var array<string, MonitoringQuery[]> $groupedQueries */
        $groupedQueries = [];
        foreach ($this->getQueriesSorted() as $query) {
            $hash = md5($query->query);
            if (!\array_key_exists($hash, $groupedQueries)) {
                $groupedQueries[$hash] = [];
            }
            $groupedQueries[$hash][] = $query;
        }
        $dtos = [];
        foreach ($groupedQueries as $hash => $queries) {
            $dto = new GroupedMonitoringQueryDto();
            $dto->query = $queries[0]->query;
            $minTime = 10000000000;
            $maxTime = 0;
            $addedTime = 0;
            $queryCount = 0;
            foreach ($queries as $query) {
                $duration = $query->getDuration();
                if ($minTime > $duration) {
                    $minTime = $duration;
                }
                if ($maxTime < $duration) {
                    $maxTime = $duration;
                }
                $addedTime += $duration;
                ++$queryCount;
            }
            $dto->count = $queryCount;
            $dto->maxExecutionTime = $maxTime;
            $dto->minExecutionTime = $minTime;
            $dto->meanExecutionTime = $addedTime / $queryCount;
            $dtos[] = $dto;
        }
        usort($dtos, fn ($a, $b) => $b->maxExecutionTime - $a->maxExecutionTime);

        return $dtos;
    }

    /**
     * @return Collection<MonitoringTwigRender>
     */
    public function getRootTwigRenders(): Collection
    {
        $criteria = new Criteria(Criteria::expr()->isNull('parent'));

        return $this->twigRenders->matching($criteria);
    }

    /**
     * @return Collection<MonitoringCurlRequest>
     */
    public function getRequestsSorted(string $sortBy = 'durationMilliseconds', string $sortDirection = 'DESC'): Collection
    {
        $criteria = new Criteria(orderings: [$sortBy => $sortDirection]);

        return $this->curlRequests->matching($criteria);
    }
}
