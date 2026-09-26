<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\DTO\EntryDto;
use App\Entity\Magazine;
use App\Entity\User;
use App\Repository\EntryRepository;
use App\Service\EntryManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class M3u8EmbedFixtures extends BaseFixture implements DependentFixtureInterface
{
    public const string GROUP = 'm3u8-embed';
    public const string ENTRY_TITLE = 'Regression test: iframe pointing to master.m3u8';
    public const string ENTRY_URL = 'http://127.0.0.1:8081/article.html';

    public function __construct(
        private readonly EntryManager $entryManager,
        private readonly EntryRepository $entryRepository,
    ) {
    }

    public static function getGroups(): array
    {
        return [self::GROUP];
    }

    public function getDependencies(): array
    {
        return [
            MagazineFixtures::class,
        ];
    }

    protected function loadData(ObjectManager $manager): void
    {
        if ($this->entryRepository->findOneByUrl(self::ENTRY_URL)) {
            return;
        }

        $user = $this->getReference('user_1', User::class);

        $dto = new EntryDto();
        $dto->magazine = $this->getReference('magazine_1', Magazine::class);
        $dto->user = $user;
        $dto->title = self::ENTRY_TITLE;
        $dto->url = self::ENTRY_URL;
        $dto->body = <<<'MARKDOWN'
This deterministic development fixture reproduces an oEmbed response whose iframe points directly to `master.m3u8`.

Expected result: opening the media preview must not insert the iframe or request the playlist.
MARKDOWN;
        $dto->ip = '127.0.0.1';
        $dto->lang = 'en';

        $entry = $this->entryManager->create($dto, $user, rateLimit: false);
        $entry->hasEmbed = true;
        $entry->sticky = true;

        $manager->flush();
    }
}
