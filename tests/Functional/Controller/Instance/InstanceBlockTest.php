<?php
declare(strict_types=1);

namespace App\Tests\Functional\Controller\Instance;

use App\Entity\EntryComment;
use App\Entity\Instance;
use App\Entity\PostComment;
use App\Entity\User;
use App\Tests\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

class InstanceBlockTest extends WebTestCase
{
    private User $user;
    private EntryComment $entryCommentParentVisibleMag;
    private EntryComment $entryCommentParentInvisibleMag;
    private PostComment $postCommentParentVisibleMag;
    private PostComment $postCommentParentInvisibleMag;

    public function testEntryCommentChildrenHiddenOnSingleCommentView(): void
    {
        $this->prepareContent('testEntryCommentChildrenHiddenOnSingleCommentView');
        $this->instanceManager->blockInstance($this->instanceRepository->findOneBy(['domain' => '3.example.com']), $this->user);
        $this->client->loginUser($this->user);

        $parentComment = $this->entryCommentParentVisibleMag;
        $this->client->request('GET', '/m/'.$parentComment->magazine->name.'/t/'.$parentComment->entry->getId().'/_/comment/'.$parentComment->getId());
        self::assertAnySelectorTextContains('.comment .content p', 'test parent comment');
        self::assertAnySelectorTextContains('.comment .content p', 'visible');
        self::assertAnySelectorTextNotContains('.comment .content p', 'invisible');

        $parentComment = $this->entryCommentParentInvisibleMag;
        $this->client->request('GET', '/m/'.$parentComment->magazine->name.'/t/'.$parentComment->entry->getId().'/_/comment/'.$parentComment->getId());
        // as the Magazine is instance-blocked, no comment may be shown
        self::assertAnySelectorTextContains('.comment .content p', 'test parent comment');
        self::assertAnySelectorTextNotContains('.comment .content p', 'visible');
        self::assertAnySelectorTextNotContains('.comment .content p', 'invisible');
    }

    public function testEntryCommentChildrenHiddenOnSingleCommentClassicView(): void
    {
        $this->prepareContent('testEntryCommentChildrenHiddenOnSingleCommentClassicView');
        $this->instanceManager->blockInstance($this->instanceRepository->findOneBy(['domain' => '3.example.com']), $this->user);
        $this->client->loginUser($this->user);

        $parentComment = $this->entryCommentParentVisibleMag;
        $this->client->getCookieJar()->set(new Cookie('entry_comments_view', 'classic'));
        $this->client->request('GET', '/m/'.$parentComment->magazine->name.'/t/'.$parentComment->entry->getId().'/_/comment/'.$parentComment->getId());
        self::assertAnySelectorTextContains('.comment .content p', 'test parent comment');
        self::assertAnySelectorTextContains('.comment .content p', 'visible');
        self::assertAnySelectorTextNotContains('.comment .content p', 'invisible');

        $parentComment = $this->entryCommentParentInvisibleMag;
        $this->client->request('GET', '/m/'.$parentComment->magazine->name.'/t/'.$parentComment->entry->getId().'/_/comment/'.$parentComment->getId());
        // as the Magazine is instance-blocked, no comment may be shown
        self::assertAnySelectorTextContains('.comment .content p', 'test parent comment');
        self::assertAnySelectorTextNotContains('.comment .content p', 'visible');
        self::assertAnySelectorTextNotContains('.comment .content p', 'invisible');
    }

    public function testEntryCommentChildrenHiddenOnMagazineCommentsView(): void
    {
        $this->prepareContent('testEntryCommentChildrenHiddenOnMagazineCommentsView');
        $this->instanceManager->blockInstance($this->instanceRepository->findOneBy(['domain' => '3.example.com']), $this->user);
        $this->client->loginUser($this->user);

        $parentComment = $this->entryCommentParentVisibleMag;
        $this->client->request('GET', '/m/'.$parentComment->magazine->name.'/comments');
        self::assertAnySelectorTextContains('.comment .content p', 'test parent comment');
        self::assertAnySelectorTextContains('.comment .content p', 'visible');
        self::assertAnySelectorTextNotContains('.comment .content p', 'invisible');

        $parentComment = $this->entryCommentParentInvisibleMag;
        $this->client->request('GET', '/m/'.$parentComment->magazine->name.'/comments');
        // as the Magazine is instance-blocked, no sub-comment are be shown but top comments are
        self::assertAnySelectorTextContains('.comment .content p', 'test parent comment');
        self::assertAnySelectorTextContains('.comment .content p', 'invisible top entryComment');
        self::assertAnySelectorTextNotContains('.comment .content p', 'visible entryComment');
        self::assertAnySelectorTextNotContains('.comment .content p', 'invisible entryComment');
    }

    public function testPostCommentChildrenHiddenOnSingleCommentView(): void
    {
        $this->prepareContent('testPostCommentChildrenHiddenOnSingleCommentView');
        $this->instanceManager->blockInstance($this->instanceRepository->findOneBy(['domain' => '3.example.com']), $this->user);
        $this->client->loginUser($this->user);

        $parentComment = $this->postCommentParentVisibleMag;
        $this->client->request('GET', '/m/'.$parentComment->magazine->name.'/p/'.$parentComment->post->getId());
        self::assertAnySelectorTextContains('.post .content p', 'test parent');
        self::assertAnySelectorTextContains('.post-comment .content p', 'test parent comment');
        self::assertAnySelectorTextContains('.post-comment .content p', 'visible');
        self::assertAnySelectorTextNotContains('.post-comment .content p', 'invisible');

        $parentComment = $this->postCommentParentInvisibleMag;
        $this->client->request('GET', '/m/'.$parentComment->magazine->name.'/p/'.$parentComment->post->getId());
        // as the Magazine is instance-blocked, no comment may be shown
        self::assertAnySelectorTextContains('.post .content p', 'test parent');
        self::assertSelectorNotExists('.post-comment .content p');
    }

    public function testPostCommentChildrenHiddenOnSingleCommentClassicView(): void
    {
        $this->prepareContent('testPostCommentChildrenHiddenOnSingleCommentClassicView');
        $this->instanceManager->blockInstance($this->instanceRepository->findOneBy(['domain' => '3.example.com']), $this->user);
        $this->client->loginUser($this->user);

        $parentComment = $this->postCommentParentVisibleMag;
        $this->client->getCookieJar()->set(new Cookie('entry_comments_view', 'classic'));
        $this->client->request('GET', '/m/'.$parentComment->magazine->name.'/p/'.$parentComment->post->getId());
        self::assertAnySelectorTextContains('.post .content p', 'test parent');
        self::assertAnySelectorTextContains('.post-comment .content p', 'test parent comment');
        self::assertAnySelectorTextContains('.post-comment .content p', 'visible');
        self::assertAnySelectorTextNotContains('.post-comment .content p', 'invisible');

        $parentComment = $this->postCommentParentInvisibleMag;
        $this->client->request('GET', '/m/'.$parentComment->magazine->name.'/p/'.$parentComment->post->getId());
        // as the Magazine is instance-blocked, no comment may be shown
        self::assertAnySelectorTextContains('.post .content p', 'test parent');
        self::assertSelectorNotExists('.post-comment .content p');
    }

    public function testEntryHiddenOnMagazineView(): void
    {
        $this->prepareContent('testEntryHiddenOnMagazineView');
        $this->instanceManager->blockInstance($this->instanceRepository->findOneBy(['domain' => '3.example.com']), $this->user);
        $this->client->loginUser($this->user);

        $this->client->request('GET', '/m/'.$this->entryCommentParentVisibleMag->magazine->name.'/threads');
        self::assertAnySelectorTextContains('.entry header h2 a', 'test parent');
        self::assertAnySelectorTextNotContains('.entry header h2 a', 'invisible');

        $this->client->request('GET', '/m/'.$this->entryCommentParentInvisibleMag->magazine->name.'/threads');
        // the Magazine is instance-blocked but should still show allowed content as it is viewed directly
        self::assertAnySelectorTextContains('.entry header h2 a', 'test parent');
        self::assertAnySelectorTextContains('.entry header h2 a', 'invisible by magazine');
        self::assertAnySelectorTextNotContains('.entry header h2 a', 'invisible by user');
    }

    public function testPostHiddenOnMagazineView(): void
    {
        $this->prepareContent('testPostHiddenOnMagazineView');
        $this->instanceManager->blockInstance($this->instanceRepository->findOneBy(['domain' => '3.example.com']), $this->user);
        $this->client->loginUser($this->user);

        $this->client->request('GET', '/m/'.$this->entryCommentParentVisibleMag->magazine->name.'/microblog');
        self::assertAnySelectorTextContains('.post .content p', 'test parent');
        self::assertAnySelectorTextNotContains('.post .content p', 'invisible');

        $this->client->request('GET', '/m/'.$this->entryCommentParentInvisibleMag->magazine->name.'/microblog');
        // the Magazine is instance-blocked but should still show allowed content as it is viewed directly
        self::assertAnySelectorTextContains('.post .content p', 'test parent');
        self::assertAnySelectorTextContains('.post .content p', 'invisible by magazine');
        self::assertAnySelectorTextNotContains('.post .content p', 'invisible by user');
    }

    private function prepareContent(string $namePrefix): void
    {
        $this->entityManager->persist(new Instance('1.example.com'));
        $this->entityManager->persist(new Instance('2.example.com'));
        $this->entityManager->persist(new Instance('3.example.com'));

        $remoteUser1 = $this->getUserByUsername("$namePrefix-RemoA");
        $remoteUser1->apDomain = '1.example.com';
        $remoteUser1->apId = "$namePrefix-remo_a@1.example.com";
        $remoteUser1->apInboxUrl = 'https://1.example.com/inbox';
        $remoteUser1->apProfileId = 'https://1.example.com/user';
        $remoteUser2 = $this->getUserByUsername("$namePrefix-RemoB");
        $remoteUser2->apDomain = '3.example.com';
        $remoteUser2->apId = "$namePrefix-remo_b@3.example.com";
        $remoteUser2->apInboxUrl = 'https://3.example.com/inbox';
        $remoteUser2->apProfileId = 'https://3.example.com/user';
        $this->entityManager->persist($remoteUser1);
        $this->entityManager->persist($remoteUser2);

        $magazine1 = $this->getMagazineByName("$namePrefix-MagA");
        $magazine1->apDomain = '1.example.com';
        $magazine1->apId = "$namePrefix-mag@1.example.com";
        $magazine1->apInboxUrl = 'https://1.example.com/inbox';
        $magazine1->apProfileId = 'https://1.example.com/mag';
        $magazine2 = $this->getMagazineByName("$namePrefix-MagB");
        $magazine2->apDomain = '3.example.com';
        $magazine2->apId = "$namePrefix-mag@3.example.com";
        $magazine2->apInboxUrl = 'https://3.example.com/inbox';
        $magazine2->apProfileId = 'https://3.example.com/mag';
        $this->entityManager->persist($magazine1);
        $this->entityManager->persist($magazine2);

        $entry = $this->createEntry("[$namePrefix] test parent", magazine: $magazine1, user: $remoteUser1);
        $this->entryCommentParentVisibleMag = $this->createEntryComment("[$namePrefix] test parent comment", entry: $entry, user: $remoteUser1);
        $this->createEntryComment("[$namePrefix] test visible", entry: $entry, user: $remoteUser1, parent: $this->entryCommentParentVisibleMag);
        $this->createEntryComment("[$namePrefix] test invisible entryComment by user", entry: $entry, user: $remoteUser2, parent: $this->entryCommentParentVisibleMag);
        $this->createEntryComment("[$namePrefix] test invisible top entryComment by user", entry: $entry, user: $remoteUser2);
        $this->entityManager->refresh($this->entryCommentParentVisibleMag);

        $entry = $this->createEntry("[$namePrefix] test parent", magazine: $magazine2, user: $remoteUser1);
        $this->entryCommentParentInvisibleMag = $this->createEntryComment("[$namePrefix] test parent comment", entry: $entry, user: $remoteUser1);
        $this->createEntryComment("[$namePrefix] test visible", entry: $entry, user: $remoteUser1, parent: $this->entryCommentParentInvisibleMag);
        $this->createEntryComment("[$namePrefix] test invisible entryComment by magazine", entry: $entry, user: $remoteUser1, parent: $this->entryCommentParentInvisibleMag);
        $this->createEntryComment("[$namePrefix] test invisible top entryComment by magazine", entry: $entry, user: $remoteUser1);
        $this->entityManager->refresh($this->entryCommentParentInvisibleMag);

        $post = $this->createPost("[$namePrefix] test parent", magazine: $magazine1, user: $remoteUser1);
        $this->postCommentParentVisibleMag = $this->createPostComment("[$namePrefix] test parent comment", post: $post, user: $remoteUser1);
        $this->createPostComment("[$namePrefix] test visible", post: $post, user: $remoteUser1, parent: $this->postCommentParentVisibleMag);
        $this->createPostComment("[$namePrefix] test invisible postComment by user", post: $post, user: $remoteUser2, parent: $this->postCommentParentVisibleMag);
        $this->createPostComment("[$namePrefix] test invisible top postComment by user", post: $post, user: $remoteUser2);
        $this->entityManager->refresh($this->postCommentParentVisibleMag);

        $post = $this->createPost("[$namePrefix] test parent", magazine: $magazine2, user: $remoteUser1);
        $this->postCommentParentInvisibleMag = $this->createPostComment("[$namePrefix] test parent comment", post: $post, user: $remoteUser1);
        $this->createPostComment("[$namePrefix] test visible", post: $post, user: $remoteUser1, parent: $this->postCommentParentInvisibleMag);
        $this->createPostComment("[$namePrefix] test invisible postComment by magazine", post: $post, user: $remoteUser1, parent: $this->postCommentParentInvisibleMag);
        $this->createPostComment("[$namePrefix] test invisible top postComment by magazine", post: $post, user: $remoteUser1);
        $this->entityManager->refresh($this->postCommentParentInvisibleMag);

        $this->createEntry("[$namePrefix] test invisible by user", magazine: $magazine1, user: $remoteUser2);
        $this->createEntry("[$namePrefix] test invisible by magazine", magazine: $magazine2, user: $remoteUser1);
        $this->createPost("[$namePrefix] test invisible by user", magazine: $magazine1, user: $remoteUser2);
        $this->createPost("[$namePrefix] test invisible by magazine", magazine: $magazine2, user: $remoteUser1);

        $this->user = $this->getUserByUsername("$namePrefix-JohnDoe");
    }
}
