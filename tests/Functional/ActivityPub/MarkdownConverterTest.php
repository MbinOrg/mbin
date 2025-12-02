<?php

declare(strict_types=1);

namespace App\Tests\Functional\ActivityPub;

use PHPUnit\Framework\Attributes\DataProvider;

use function PHPUnit\Framework\assertEquals;

class MarkdownConverterTest extends ActivityPubFunctionalTestCase
{
    public function setUpRemoteEntities(): void
    {
    }

    public function setUpLocalEntities(): void
    {
        $domain = 'some.domain.tld';
        $this->switchToRemoteDomain($domain);

        $this->registerActor($this->getUserByUsername('someUser', email: "someUser@$domain"), $domain, true);
        $this->registerActor($this->getMagazineByName('someMagazine'), $domain, true);

        $this->switchToLocalDomain();
    }

    public function setUp(): void
    {
        parent::setUp();

        // generate the local user 'someUser'
        $user = $this->getUserByUsername('someUser', email: 'someUser@kbin.test');
        $this->getMagazineByName('someMagazine', $user);
    }

    #[DataProvider('htmlMentionsProvider')]
    public function testMentions(string $html, array $apTags, array $expectedMentions, string $name): void
    {
        $converted = $this->apMarkdownConverter->convert($html, $apTags);
        $mentions = $this->mentionManager->extract($converted);
        assertEquals($expectedMentions, $mentions, message: "Mention test '$name'");
    }

    public static function htmlMentionsProvider(): array
    {
        return [
            [
                'html' => '<p><span class="h-card" translate="no"><a href="https://some.domain.tld/u/someUser" class="u-url mention">@<span>someUser</span></a></span> <span class="h-card" translate="no"><a href="https://kbin.test/u/someUser" class="u-url mention">@<span>someUser@kbin.test</span></a></span></p>',
                'apTags' => [
                    [
                        'type' => 'Mention',
                        'href' => 'https://some.domain.tld/u/someUser',
                        'name' => '@someUser',
                    ],
                    [
                        'type' => 'Mention',
                        'href' => 'https://kbin.test/u/someUser',
                        'name' => '@someUser@kbin.test',
                    ],
                ],
                'expectedMentions' => ['@someUser@some.domain.tld', '@someUser@kbin.test'],
                'name' => 'Local and remote user',
            ],
            [
                'html' => '<p><span class="h-card" translate="no"><a href="https://some.domain.tld/m/someMagazine" class="u-url mention">@<span>someMagazine</span></a></span></p>',
                'apTags' => [
                    [
                        'type' => 'Mention',
                        'href' => 'https://some.domain.tld/m/someMagazine',
                        'name' => '@someMagazine',
                    ],
                ],
                'expectedMentions' => ['@someMagazine@some.domain.tld'],
                'name' => 'Magazine mention',
            ],
            [
                'html' => '<p><span class="h-card" translate="no"><a href="https://kbin.test/m/someMagazine" class="u-url mention">@<span>someMagazine</span></a></span></p>',
                'apTags' => [
                    [
                        'type' => 'Mention',
                        'href' => 'https://kbin.test/m/someMagazine',
                        'name' => '@someMagazine',
                    ],
                ],
                'expectedMentions' => ['@someMagazine@kbin.test'],
                'name' => 'Local magazine mention',
            ],
        ];
    }
}
