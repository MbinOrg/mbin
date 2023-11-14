<?php

declare(strict_types=1);

namespace App\Controller\User\Profile;

use App\Controller\AbstractController;
use App\Repository\StatsRepository;
use App\Service\StatsManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserStatsController extends AbstractController
{
    public function __construct(private readonly StatsManager $manager)
    {
    }

    #[IsGranted('ROLE_USER')]
    public function __invoke(?string $statsType, ?int $statsPeriod, Request $request): Response
    {
        $user = $this->getUserOrThrow();

        $this->denyAccessUnlessGranted('edit_profile', $user);

        $statsType = $this->manager->resolveType($statsType);

        if (!$statsPeriod) {
            $statsPeriod = 31;
        }

        if (-1 === $statsPeriod) {
            $statsPeriod = null;
        }

        if ($statsPeriod) {
            $statsPeriod = min($statsPeriod, 256);
            $start = (new \DateTime())->modify("-$statsPeriod days");
        }

        $results = match ($statsType) {
            StatsRepository::TYPE_VIEWS => $statsPeriod
                ? $this->manager->drawDailyViewsStatsByTime($start, $user)
                : $this->manager->drawMonthlyViewsChart($user),
            StatsRepository::TYPE_VOTES => $statsPeriod
                ? $this->manager->drawDailyVotesStatsByTime($start, $user)
                : $this->manager->drawMonthlyVotesChart($user),
            default => $statsPeriod
                ? $this->manager->drawDailyContentStatsByTime($start, $user)
                : $this->manager->drawMonthlyContentChart($user)
        };

        return $this->render(
            'user/settings/stats.html.twig', [
                'user' => $user,
                'period' => $statsPeriod,
                'chart' => $results,
                'withFederated' => false,
            ]
        );
    }
}
