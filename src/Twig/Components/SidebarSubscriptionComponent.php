<?php

namespace App\Twig\Components;

use App\Controller\User\ThemeSettingsController;
use App\Entity\Magazine;
use App\Entity\MagazineSubscription;
use App\Entity\User;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;
use function Aws\map;

#[AsTwigComponent('sidebar_subscriptions', 'layout/sidebar_subscriptions.html.twig')]
class SidebarSubscriptionComponent
{
    public User $user;
    public ?Magazine $openMagazine;

    /**
     * @var Magazine[]
     */
    public array $magazines;

    public ?string $sort;

    #[PostMount]
    public function PostMount(): void
    {
        $this->magazines = [];
        foreach ($this->user->subscriptions as /** @type MagazineSubscription $sub */ $sub) {
            $this->magazines[] = $sub->magazine;
        }
        if($this->sort == ThemeSettingsController::ALPHABETICALLY) {
            usort($this->magazines, fn($a, $b) => $a->name > $b->name ? 1 : -1);
        } else {
            usort($this->magazines, fn($a, $b) => $a->lastActive < $b->lastActive ? 1 : -1);
        }
    }
}