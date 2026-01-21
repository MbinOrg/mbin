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
class MonitoringQuery
{
    use CreatedAtTrait {
        CreatedAtTrait::__construct as createdAtTraitConstruct;
    }
    use MonitoringPerformanceTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    private int $id;

    #[Column(type: 'text')]
    public string $query;

    #[Column(type: 'json', nullable: true, options: ['jsonb' => true])]
    public ?array $parameters = null;

    #[ManyToOne(targetEntity: MonitoringExecutionContext::class, inversedBy: 'queries')]
    #[JoinColumn(referencedColumnName: 'uuid', onDelete: 'CASCADE')]
    public MonitoringExecutionContext $context;

    public function __construct()
    {
        $this->createdAtTraitConstruct();
    }

    public function cleanParameterArray(): void
    {
        if (null !== $this->parameters) {
            $json = json_encode($this->parameters, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE);
            $newParameters = json_decode($json, true);
            $newParameters2 = [];
            foreach ($newParameters as $newParameter) {
                if (\is_string($newParameter)) {
                    $newParameter = preg_replace('/[[:cntrl:]]/', '', $newParameter);
                }
                $newParameters2[] = $newParameter;
            }
            $this->parameters = $newParameters2;
        }
    }
}
