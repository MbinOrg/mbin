<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Controller\Dev\M3u8EmbedFixtureController;
use App\Entity\Entry;
use App\Entity\Magazine;
use App\Entity\User;
use App\Repository\EntryRepository;
use App\Utils\Slugger;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class M3u8EmbedFixtures extends BaseFixture implements DependentFixtureInterface
{
    public const string GROUP = 'm3u8-embed';
    public const string ENTRY_TITLE = 'Regression test: iframe pointing to master.m3u8';
    public const string ENTRY_URL = M3u8EmbedFixtureController::MASTER_URL;

    public function __construct(
        private readonly EntryRepository $entryRepository,
        Slugger $slugger,
    ) {
        $this->slugger = $slugger;
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

        $entry = new Entry(
            self::ENTRY_TITLE,
            self::ENTRY_URL,
            <<<'MARKDOWN'
This deterministic development fixture supplies the same iframe candidate as an oEmbed response pointing directly to `master.m3u8`.

Expected result: opening the media preview must not insert the iframe or request the playlist.
MARKDOWN,
            $this->getReference('magazine_1', Magazine::class),
            $user,
            false,
            false,
            'en',
            '127.0.0.1',
        );
        $entry->slug = $this->slugger->slug(self::ENTRY_TITLE);
        $entry->type = Entry::ENTRY_TYPE_LINK;
        $entry->hasEmbed = true;
        $entry->sticky = true;

        $manager->persist($entry);
        $manager->flush();
    }
}
