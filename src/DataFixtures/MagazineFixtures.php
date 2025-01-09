<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\DTO\MagazineDto;
use App\Entity\User;
use App\Repository\ImageRepository;
use App\Service\ImageManager;
use App\Service\MagazineManager;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

class MagazineFixtures extends BaseFixture implements DependentFixtureInterface
{
    public const MAGAZINES_COUNT = UserFixtures::USERS_COUNT / 3;

    public function __construct(
        private readonly MagazineManager $magazineManager,
        private readonly ImageManager $imageManager,
        private readonly ImageRepository $imageRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function loadData(ObjectManager $manager): void
    {
        foreach ($this->provideRandomMagazines(self::MAGAZINES_COUNT) as $index => $magazine) {
            $image = null;
            $width = rand(100, 400);

            try {
                $tempFile = $this->imageManager->download("https://picsum.photos/{$width}/?hash=$width");
            } catch (\Exception $e) {
                $tempFile = null;
            }

            if ($tempFile) {
                $image = $this->imageRepository->findOrCreateFromPath($tempFile);
                $this->entityManager->flush();
            }

            $dto = new MagazineDto();
            $dto->name = $magazine['name'];
            $dto->title = $magazine['title'];
            $dto->description = $magazine['description'];
            $dto->rules = $magazine['rules'];
            $dto->badges = $magazine['badges'];
            $dto->icon = $image;

            $entity = $this->magazineManager->create($dto, $magazine['user']);

            $this->addReference('magazine_'.$index, $entity);
        }

        $manager->flush();
    }

    /**
     * @return array<string, mixed>[]
     */
    private function provideRandomMagazines(int $count = 1): iterable
    {
        $titles = [];
        for ($i = 0; $i <= $count; ++$i) {
            $title = substr($this->faker->words($this->faker->numberBetween(1, 5), true), 0, 50);

            if (\in_array($title, $titles)) {
                $title = $title.bin2hex(random_bytes(5));
            }

            $titles[] = $title;

            yield [
                'name' => substr($this->camelCase($title), 0, 24),
                'title' => $title,
                'user' => $this->getReference('user_'.rand(1, UserFixtures::USERS_COUNT), User::class),
                'description' => rand(0, 3) ? null : $this->faker->realText($this->faker->numberBetween(10, 550)),
                'rules' => rand(0, 3) ? null : $this->faker->realText($this->faker->numberBetween(10, 550)),
                'badges' => new ArrayCollection(),
            ];
        }
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}
