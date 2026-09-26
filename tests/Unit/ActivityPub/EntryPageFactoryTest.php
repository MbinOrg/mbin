<?php
declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub;

use App\Tests\Functional\ActivityPub\ActivityPubFunctionalTestCase;

class EntryPageFactoryTest extends ActivityPubFunctionalTestCase
{
    public function setUp(): void
    {
        $this->skipSettingUpEntities = true;
        parent::setUp();
        $bentiGorlich = $this->getUserByUsername('BentiGorlich');
        $this->registerActor($bentiGorlich, 'github.com');
        $bluedGear = $this->getUserByUsername('bluedGear');
        $this->registerActor($bluedGear, 'github.com');
        $bluedGear2 = $this->getUserByUsername('bluedGear2');
        $this->registerActor($bluedGear2, 'github.com', overridePointToUrl: $this->personFactory->getActivityPubId($bluedGear));
        $melroy = $this->getUserByUsername('Melroy');
        $this->registerActor($melroy, 'github.com');
    }

    public function testEntryToStayingArray(): void
    {
        $body = 'This is a body with a mention. @BentiGorlich@github.com @bluedGear@github.com @bluedGear2@github.com @Melroy@github.com';
        $entry = $this->getEntryByTitle('testing title', body: $body);
        $page = $this->entryPageFactory->create($entry, []);

        self::assertIsArray($page['to']);
        self::assertStringNotContainsString('{', json_encode($page['to']));
    }

    public function testEntryCommentToStayingArray(): void
    {
        $body = 'This is a body with a mention. @BentiGorlich@github.com @bluedGear@github.com @bluedGear2@github.com @Melroy@github.com';
        $entry = $this->getEntryByTitle('testing title');
        $comment = $this->createEntryComment($body, $entry);
        $note = $this->entryCommentNoteFactory->create($comment, []);

        self::assertIsArray($note['to']);
        self::assertStringNotContainsString('{', json_encode($note['to']));
    }

    public function testPostToStayingArray(): void
    {
        $body = 'This is a body with a mention. @BentiGorlich@github.com @bluedGear@github.com @bluedGear2@github.com @Melroy@github.com';
        $post = $this->createPost(body: $body);
        $note = $this->postNoteFactory->create($post, []);

        self::assertIsArray($note['to']);
        self::assertStringNotContainsString('{', json_encode($note['to']));
    }

    public function testPostCommentToStayingArray(): void
    {
        $body = 'This is a body with a mention. @BentiGorlich@github.com @bluedGear@github.com @bluedGear2@github.com @Melroy@github.com';
        $post = $this->createPost(body: 'testing post');
        $comment = $this->createPostComment($body, $post);
        $note = $this->postCommentNoteFactory->create($comment, []);

        self::assertIsArray($note['to']);
        self::assertStringNotContainsString('{', json_encode($note['to']));
    }

    public function setUpRemoteEntities(): void
    {
    }
}
