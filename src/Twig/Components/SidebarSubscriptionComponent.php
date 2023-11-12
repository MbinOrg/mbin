<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Controller\User\ThemeSettingsController;
use App\Entity\Magazine;
use App\Entity\User;
use App\Repository\MagazineRepository;
use App\Utils\SubscriptionSort;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsTwigComponent('sidebar_subscriptions', 'layout/sidebar_subscriptions.html.twig')]
class SidebarSubscriptionComponent
{
    public User $user;
    public ?Magazine $openMagazine;
    public bool $tooManyMagazines = false;

    /**
     * @var Magazine[]
     */
    public array $magazines;

    public ?string $sort;

    public function __construct(private readonly MagazineRepository $magazineRepository)
    {
    }

    #[PostMount]
    public function PostMount(): void
    {
        $max = 50;
        $this->magazines = [];
        if (ThemeSettingsController::ALPHABETICALLY === $this->sort) {
            $this->magazines = $this->magazineRepository->findMagazineSubscriptionsOfUser($this->user, SubscriptionSort::Alphabetically, $max);
        } else {
            $this->magazines = $this->magazineRepository->findMagazineSubscriptionsOfUser($this->user, SubscriptionSort::LastActive, $max);
        }
        if (\sizeof($this->magazines) === $max) {
            $this->tooManyMagazines = true;
        }
    }
}
