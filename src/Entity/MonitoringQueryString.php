<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;

#[Entity]
class MonitoringQueryString
{
    #[Column(type: 'string', length: 40)]
    #[Id]
    public string $queryHash;

    #[Column(type: 'text')]
    public string $query;

    #[OneToMany(mappedBy: 'queryString', targetEntity: MonitoringQuery::class, orphanRemoval: true)]
    public Collection $queryInstances;
}
