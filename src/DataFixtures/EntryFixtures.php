<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\DTO\EntryDto;
use App\Entity\Magazine;
use App\Entity\User;
use App\Repository\ImageRepository;
use App\Service\EntryManager;
use App\Service\ImageManagerInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

class EntryFixtures extends BaseFixture implements DependentFixtureInterface
{
    public const ENTRIES_COUNT = MagazineFixtures::MAGAZINES_COUNT * 15;

    public function __construct(
        private readonly EntryManager $entryManager,
        private readonly ImageManagerInterface $imageManager,
        private readonly ImageRepository $imageRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getDependencies(): array
    {
        return [
            MagazineFixtures::class,
        ];
    }

    public function loadData(ObjectManager $manager): void
    {
        foreach ($this->provideRandomEntries(self::ENTRIES_COUNT) as $index => $entry) {
            $dto = new EntryDto();
            $dto->magazine = $entry['magazine'];
            $dto->user = $entry['user'];
            $dto->title = $entry['title'];
            $dto->url = $entry['url'];
            $dto->body = $entry['body'];
            $dto->ip = $entry['ip'];
            $dto->lang = 'en';

            $entity = $this->entryManager->create($dto, $entry['user']);

            $roll = rand(1, 400);
            if ($roll % 5) {
                try {
                    $tempFile = $this->imageManager->download("https://picsum.photos/300/$roll?hash=$roll");
                } catch (\Exception $e) {
                    $tempFile = null;
                }

                if ($tempFile) {
                    $image = $this->imageRepository->findOrCreateFromPath($tempFile);

                    $entity->image = $image;
                    $this->entityManager->flush();
                }
            }

            $entity->createdAt = $this->getRandomTime();

            $entity->updateCounts();
            $entity->updateLastActive();
            $entity->updateRanking();

            $this->addReference('entry_'.$index, $entity);
        }

        $manager->flush();
    }

    /**
     * @return array<string, mixed>[]
     */
    private function provideRandomEntries(int $count = 1): iterable
    {
        for ($i = 0; $i <= $count; ++$i) {
            $isUrl = $this->faker->numberBetween(0, 1);
            $body = $isUrl ? null : $this->faker->paragraphs($this->faker->numberBetween(1, 10), true);

            yield [
                'title' => $this->faker->realText($this->faker->numberBetween(10, 255)),
                'url' => $isUrl ? $this->faker->url() : null,
                'body' => $body,
                'magazine' => $this->getReference('magazine_'.rand(1, (int) MagazineFixtures::MAGAZINES_COUNT), Magazine::class),
                'user' => $this->getReference('user_'.rand(1, UserFixtures::USERS_COUNT), User::class),
                'ip' => $this->faker->ipv4(),
            ];
        }
    }
}
