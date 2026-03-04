<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\MonitoringPerformanceTrait;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity]
class MonitoringCurlRequest
{
    use CreatedAtTrait {
        CreatedAtTrait::__construct as createdAtTraitConstruct;
    }
    use MonitoringPerformanceTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    private int $id;

    #[Column]
    public string $url;

    #[Column]
    public string $method;

    #[Column]
    public bool $wasSuccessful;

    #[Column(nullable: true)]
    public ?string $exception = null;

    #[ManyToOne(targetEntity: MonitoringExecutionContext::class, inversedBy: 'curlRequests')]
    #[JoinColumn(referencedColumnName: 'uuid', onDelete: 'CASCADE')]
    public MonitoringExecutionContext $context;

    public function __construct()
    {
        $this->createdAtTraitConstruct();
    }
}
