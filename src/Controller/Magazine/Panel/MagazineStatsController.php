<?php

declare(strict_types=1);

namespace App\Controller\Magazine\Panel;

use App\Controller\AbstractController;
use App\Entity\Magazine;
use App\Repository\StatsRepository;
use App\Service\StatsManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MagazineStatsController extends AbstractController
{
    public function __construct(private readonly StatsManager $manager)
    {
    }

    #[IsGranted('ROLE_USER')]
    #[IsGranted('edit', subject: 'magazine')]
    public function __invoke(Magazine $magazine, ?string $statsType, ?int $statsPeriod, ?bool $withFederated, Request $request): Response
    {
        $this->denyAccessUnlessGranted('edit_profile', $this->getUserOrThrow());

        $statsType = $this->manager->resolveType($statsType);

        if (!$statsPeriod) {
            $statsPeriod = 31;
        }

        if (-1 === $statsPeriod) {
            $statsPeriod = null;
        }

        if ($statsPeriod) {
            $statsPeriod = min($statsPeriod, 365);
            $start = (new \DateTime())->modify("-$statsPeriod days");
        }
        if (null === $withFederated) {
            $withFederated = false;
        }
        $results = match ($statsType) {
            StatsRepository::TYPE_VOTES => $statsPeriod
                ? $this->manager->drawDailyVotesStatsByTime($start, null, $magazine, !$withFederated)
                : $this->manager->drawMonthlyVotesChart(null, $magazine, !$withFederated),
            default => $statsPeriod
                ? $this->manager->drawDailyContentStatsByTime($start, null, $magazine, !$withFederated)
                : $this->manager->drawMonthlyContentChart(null, $magazine, !$withFederated),
        };

        return $this->render(
            'magazine/panel/stats.html.twig', [
                'magazine' => $magazine,
                'period' => $statsPeriod,
                'chart' => $results,
                'withFederated' => $withFederated,
            ]
        );
    }
}
