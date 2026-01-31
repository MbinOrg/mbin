<?php

declare(strict_types=1);

namespace App\DTO;

use Doctrine\Common\Collections\Criteria;

class MonitoringExecutionContextFilterDto
{
    public ?string $executionType = null;

    public ?string $path = null;

    public ?string $handler = null;

    public ?string $userType = null;

    public ?bool $hasException = null;

    public ?\DateTimeImmutable $createdFrom = null;

    public ?\DateTimeImmutable $createdTo = null;

    public ?float $durationMinimum = null;

    /**
     * @var string 'total'|'mean'
     */
    public string $chartOrdering = 'total';

    public function toCriteria(): Criteria
    {
        $criteria = new Criteria();
        if (null !== $this->executionType) {
            $criteria->andWhere(Criteria::expr()->eq('executionType', $this->executionType));
        }
        if (null !== $this->userType) {
            $criteria->andWhere(Criteria::expr()->eq('userType', $this->userType));
        }
        if (null !== $this->path) {
            $criteria->andWhere(Criteria::expr()->eq('path', $this->path));
        }
        if (null !== $this->handler) {
            $criteria->andWhere(Criteria::expr()->eq('handler', $this->handler));
        }
        if (null !== $this->hasException) {
            if ($this->hasException) {
                $criteria->andWhere(Criteria::expr()->isNotNull('exception'));
            } else {
                $criteria->andWhere(Criteria::expr()->isNull('exception'));
            }
        }
        if (null !== $this->durationMinimum) {
            $criteria->andWhere(Criteria::expr()->gt('durationMilliseconds', $this->durationMinimum));
        }
        if (null !== $this->createdFrom) {
            $criteria->andWhere(Criteria::expr()->gt('createdAt', $this->createdFrom));
        }
        if (null !== $this->createdTo) {
            $criteria->andWhere(Criteria::expr()->lt('createdAt', $this->createdTo));
        }

        return $criteria;
    }

    /**
     * @return array{whereConditions: string[], parameters: array<string,string>}
     */
    public function toSqlWheres(): array
    {
        $criteria = [];
        $parameters = [];
        if (null !== $this->executionType) {
            $criteria[] = 'execution_type = :executionType';
            $parameters[':executionType'] = $this->executionType;
        }
        if (null !== $this->userType) {
            $criteria[] = 'user_type = :userType';
            $parameters[':userType'] = $this->userType;
        }
        if (null !== $this->path) {
            $criteria[] = 'path = :path';
            $parameters['path'] = $this->path;
        }
        if (null !== $this->handler) {
            $criteria[] = 'handler = :handler';
            $parameters[':handler'] = $this->handler;
        }
        if (null !== $this->hasException) {
            if ($this->hasException) {
                $criteria[] = 'exception IS NOT NULL';
            } else {
                $criteria[] = 'exception IS NULL';
            }
        }
        if (null !== $this->durationMinimum) {
            $criteria[] = 'durationMilliseconds > :durationMin';
            $parameters[':durationMin'] = $this->durationMinimum;
        }
        if (null !== $this->createdFrom) {
            $criteria[] = 'created_at > :createdFrom';
            $parameters['createdFrom'] = $this->createdFrom->format(DATE_ATOM);
        }
        if (null !== $this->createdTo) {
            $criteria[] = 'created_at < :createdTo';
            $parameters['createdTo'] = $this->createdTo->format(DATE_ATOM);
        }

        return [
            'whereConditions' => $criteria,
            'parameters' => $parameters,
        ];
    }
}
