<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Entry;
use App\Entity\Magazine;
use App\Entity\User;
use App\Enums\EUserType;
use App\Repository\EntryRepository;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Utils\M3u8EmbedFixture;
use App\Utils\Slugger;
use Doctrine\Persistence\ObjectManager;

class M3u8EmbedFixtures extends BaseFixture
{
    public const string GROUP = 'm3u8-embed';
    public const string ENTRY_TITLE = 'Regression test: iframe pointing to master.m3u8';
    public const string NAME = 'm3u8_regression';

    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly UserRepository $userRepository,
        private readonly M3u8EmbedFixture $fixture,
        private readonly string $kernelEnvironment,
        Slugger $slugger,
    ) {
        $this->slugger = $slugger;
    }

    public static function getGroups(): array
    {
        return [self::GROUP];
    }

    protected function loadData(ObjectManager $manager): void
    {
        if ('dev' !== $this->kernelEnvironment) {
            throw new \LogicException('The M3U8 embed fixture can only be loaded in dev.');
        }

        $url = $this->fixture->getMasterUrl();
        if ($this->entryRepository->findOneBy(['url' => $url])) {
            return;
        }

        $user = $this->userRepository->findOneByUsername(self::NAME);
        if (!$user) {
            $user = new User('m3u8-regression@example.invalid', self::NAME, '*', EUserType::Person);
            $user->isVerified = true;
            $manager->persist($user);
        }

        $magazine = $this->magazineRepository->findOneByName(self::NAME);
        if (!$magazine) {
            $magazine = new Magazine(self::NAME, 'M3U8 regression testing', $user, null, null, false, false, null);
            $manager->persist($magazine);
        }

        $entry = new Entry(
            self::ENTRY_TITLE,
            $url,
            <<<'MARKDOWN'
This deterministic development fixture supplies the same iframe candidate as an oEmbed response pointing directly to `master.m3u8`.

Expected result: opening the media preview must not insert the iframe or request the playlist.
MARKDOWN,
            $magazine,
            $user,
            false,
            false,
            'en',
            null,
        );
        $entry->slug = $this->slugger->slug(self::ENTRY_TITLE);
        $entry->type = Entry::ENTRY_TYPE_LINK;
        $entry->hasEmbed = true;
        $entry->sticky = true;

        $magazine->addEntry($entry);

        $manager->persist($entry);
        $manager->flush();
    }
}
