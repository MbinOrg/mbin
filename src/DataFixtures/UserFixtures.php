<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Repository\ImageRepository;
use App\Repository\UserRepository;
use App\Service\ImageManager;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends BaseFixture
{
    public const USERS_COUNT = 9;

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ImageManager $imageManager,
        private readonly ImageRepository $imageRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function loadData(ObjectManager $manager): void
    {
        foreach ($this->provideRandomUsers(self::USERS_COUNT) as $index => $user) {
            $newUser = new User(
                $user['email'],
                $user['username'],
                $user['password'],
                $user['type']
            );

            $newUser->setPassword(
                $this->hasher->hashPassword($newUser, $user['password'])
            );

            $newUser->notifyOnNewEntry = true;
            $newUser->notifyOnNewEntryReply = true;
            $newUser->notifyOnNewEntryCommentReply = true;
            $newUser->notifyOnNewPostReply = true;
            $newUser->notifyOnNewPostCommentReply = true;
            $newUser->isVerified = true;

            $manager->persist($newUser);

            $this->addReference('user_'.$index, $newUser);

            $manager->flush();

            if ('demo' !== $user['username']) {
                $rand = rand(1, 500);

                try {
                    $tempFile = $this->imageManager->download("https://picsum.photos/500/500?hash={$rand}");
                } catch (\Exception $e) {
                    $tempFile = null;
                }

                if ($tempFile) {
                    $image = $this->imageRepository->findOrCreateFromPath($tempFile);
                    $newUser->avatar = $image;
                    $manager->flush();
                }
            }
        }
    }

    /**
     * @return array<string, string>[]
     */
    private function provideRandomUsers(int $count = 1): iterable
    {
        if (!$this->userRepository->findOneByUsername('demo')) {
            yield [
                'email' => 'demo@karab.in',
                'username' => 'demo',
                'password' => 'demo',
                'type' => 'Person',
            ];
        }

        for ($i = 0; $i <= $count; ++$i) {
            yield [
                'email' => $this->faker->email(),
                'username' => str_replace('.', '_', $this->faker->userName()),
                'password' => 'secret',
                'type' => 'Person',
            ];
        }
    }
}
