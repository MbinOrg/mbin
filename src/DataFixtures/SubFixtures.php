<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Magazine;
use App\Entity\User;
use App\Service\MagazineManager;
use App\Service\UserManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class SubFixtures extends BaseFixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly MagazineManager $magazineManager,
        private readonly UserManager $userManager,
    ) {
    }

    public function loadData(ObjectManager $manager): void
    {
        for ($u = 1; $u <= UserFixtures::USERS_COUNT; ++$u) {
            $this->magazines($u);
            $this->users($u);
        }
    }

    private function magazines(int $u): void
    {
        $randomNb = $this->getUniqueNb(
            MagazineFixtures::MAGAZINES_COUNT,
            \intval(MagazineFixtures::MAGAZINES_COUNT / rand(2, 5))
        );

        foreach ($randomNb as $m) {
            $roll = rand(0, 2);

            if (0 === $roll) {
                $this->magazineManager->block(
                    $this->getReference('magazine_'.$m, Magazine::class),
                    $this->getReference('user_'.$u, User::class)
                );
                continue;
            }

            $this->magazineManager->subscribe(
                $this->getReference('magazine_'.$m, Magazine::class),
                $this->getReference('user_'.$u, User::class)
            );
        }
    }

    /**
     * @return int[]
     */
    private function getUniqueNb(int $max, int $quantity): array
    {
        $numbers = range(1, $max);
        shuffle($numbers);

        return \array_slice($numbers, 0, $quantity);
    }

    private function users(int $u): void
    {
        $randomNb = $this->getUniqueNb(
            UserFixtures::USERS_COUNT,
            \intval(UserFixtures::USERS_COUNT / rand(2, 5))
        );

        foreach ($randomNb as $f) {
            $roll = rand(0, 2);

            if (0 === $roll) {
                $this->userManager->block(
                    $this->getReference('user_'.$f, User::class),
                    $this->getReference('user_'.$u, User::class)
                );
                continue;
            }

            $this->userManager->follow(
                $this->getReference('user_'.$f, User::class),
                $this->getReference('user_'.$u, User::class)
            );
        }
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            MagazineFixtures::class,
        ];
    }
}
