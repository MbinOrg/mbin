<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\MonitoringPerformanceTrait;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;

#[Entity]
class MonitoringTwigRender
{
    use CreatedAtTrait {
        CreatedAtTrait::__construct as createdAtTraitConstruct;
    }
    use MonitoringPerformanceTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    private int $id;

    #[ManyToOne(targetEntity: MonitoringExecutionContext::class, inversedBy: 'twigRenders')]
    #[JoinColumn(referencedColumnName: 'uuid', onDelete: 'CASCADE')]
    public MonitoringExecutionContext $context;

    #[Column(type: 'text')]
    public string $shortDescription;

    #[Column(nullable: true)]
    public ?string $templateName = null;

    #[Column(nullable: true)]
    public ?string $name = null;

    #[Column(nullable: true)]
    public ?string $type = null;

    #[Column(nullable: true)]
    public ?int $memoryUsage = null;

    #[Column(nullable: true)]
    public ?int $peakMemoryUsage = null;

    #[Column(nullable: true)]
    public ?float $profilerDuration = null;

    #[ManyToOne(targetEntity: MonitoringTwigRender::class, inversedBy: 'children')]
    #[JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?MonitoringTwigRender $parent = null;

    #[OneToMany(mappedBy: 'parent', targetEntity: MonitoringTwigRender::class, orphanRemoval: true)]
    public Collection $children;

    public function __construct()
    {
        $this->createdAtTraitConstruct();
    }

    public function getPercentageOfParentDuration(): float
    {
        if (null === $this->parent) {
            return 100 / $this->context->twigRenderDurationMilliseconds * $this->durationMilliseconds;
        }

        return 100 / $this->parent->durationMilliseconds * $this->durationMilliseconds;
    }

    public function getPercentageOfTotalDuration(): float
    {
        return 100 / $this->context->twigRenderDurationMilliseconds * $this->durationMilliseconds;
    }

    public function getColorBasedOnPercentageDuration(bool $compareToParent = true): string
    {
        if ($compareToParent) {
            $percentage = $this->getPercentageOfParentDuration() / 100;
        } else {
            $percentage = $this->getPercentageOfTotalDuration() / 100;
        }
        $baseline = 50;
        $rFactor = 1;
        $gFactor = 0.25;
        $bFactor = 0.1;

        $valueR = ($rFactor * (255 - $baseline) * $percentage) + $baseline;
        $valueG = ($gFactor * (255 - $baseline) * $percentage) + $baseline;
        $valueB = ($bFactor * (255 - $baseline) * $percentage) + $baseline;

        return "rgb($valueR, $valueG, $valueB)";
    }
}
