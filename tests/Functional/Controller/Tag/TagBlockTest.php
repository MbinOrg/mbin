<?php
declare(strict_types=1);

namespace App\Tests\Functional\Controller\Tag;

use App\Entity\EntryComment;
use App\Entity\PostComment;
use App\Entity\User;
use App\Tests\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

class TagBlockTest extends WebTestCase
{
    private User $user;
    private EntryComment $entryCommentParent;
    private PostComment $postCommentParent;

    public function testEntryCommentChildrenHiddenOnSingleCommentView(): void
    {
        $this->prepareContent('testEntryCommentChildrenHiddenOnSingleCommentView');
        $this->client->loginUser($this->user);

        $parentComment = $this->entryCommentParent;
        $this->client->request('GET', '/m/'.$parentComment->magazine->name.'/t/'.$parentComment->entry->getId().'/_/comment/'.$parentComment->getId());
        self::assertAnySelectorTextContains('.comment .content p', 'parent comment');
        self::assertAnySelectorTextContains('.comment .content p', 'visible');
        self::assertAnySelectorTextNotContains('.comment .content p', 'invisible');
    }

    public function testEntryCommentChildrenHiddenOnSingleCommentClassicView(): void
    {
        $this->prepareContent('testEntryCommentChildrenHiddenOnSingleCommentClassicView');
        $this->client->loginUser($this->user);

        $parentComment = $this->entryCommentParent;
        $this->client->getCookieJar()->set(new Cookie('entry_comments_view', 'classic'));
        $this->client->request('GET', '/m/'.$parentComment->magazine->name.'/t/'.$parentComment->entry->getId().'/_/comment/'.$parentComment->getId());
        self::assertAnySelectorTextContains('.comment .content p', 'parent comment');
        self::assertAnySelectorTextContains('.comment .content p', 'visible');
        self::assertAnySelectorTextNotContains('.comment .content p', 'invisible');
    }

    public function testEntryCommentChildrenHiddenOnMagazineCommentsView(): void
    {
        $this->prepareContent('testEntryCommentChildrenHiddenOnMagazineCommentsView');
        $this->client->loginUser($this->user);

        $parentComment = $this->entryCommentParent;
        $this->client->request('GET', '/m/'.$parentComment->magazine->name.'/comments');
        self::assertAnySelectorTextContains('.comment .content p', 'parent comment');
        self::assertAnySelectorTextContains('.comment .content p', 'visible');
        self::assertAnySelectorTextNotContains('.comment .content p', 'invisible');
    }

    public function testPostCommentChildrenHiddenOnSingleCommentView(): void
    {
        $this->prepareContent('testPostCommentChildrenHiddenOnSingleCommentView');
        $this->client->loginUser($this->user);

        $parentComment = $this->postCommentParent;
        $this->client->request('GET', '/m/'.$parentComment->magazine->name.'/p/'.$parentComment->post->getId());
        self::assertAnySelectorTextContains('.post .content p', 'parent');
        self::assertAnySelectorTextContains('.post-comment .content p', 'parent comment');
        self::assertAnySelectorTextContains('.post-comment .content p', 'visible');
        self::assertAnySelectorTextNotContains('.post-comment .content p', 'invisible');
    }

    public function testPostCommentChildrenHiddenOnSingleCommentClassicView(): void
    {
        $this->prepareContent('testPostCommentChildrenHiddenOnSingleCommentClassicView');
        $this->client->loginUser($this->user);

        $parentComment = $this->postCommentParent;
        $this->client->getCookieJar()->set(new Cookie('entry_comments_view', 'classic'));
        $this->client->request('GET', '/m/'.$parentComment->magazine->name.'/p/'.$parentComment->post->getId());
        self::assertAnySelectorTextContains('.post .content p', 'parent');
        self::assertAnySelectorTextContains('.post-comment .content p', 'parent comment');
        self::assertAnySelectorTextContains('.post-comment .content p', 'visible');
        self::assertAnySelectorTextNotContains('.post-comment .content p', 'invisible');
    }

    public function testEntryHiddenOnMagazineView(): void
    {
        $this->prepareContent('testEntryHiddenOnMagazineView');
        $this->client->loginUser($this->user);

        $this->client->request('GET', '/m/'.$this->entryCommentParent->magazine->name.'/threads');
        self::assertAnySelectorTextContains('.entry header h2 a', 'parent');
        self::assertAnySelectorTextNotContains('.entry header h2 a', 'invisible');
    }

    public function testPostHiddenOnMagazineView(): void
    {
        $this->prepareContent('testPostHiddenOnMagazineView');
        $this->client->loginUser($this->user);

        $this->client->request('GET', '/m/'.$this->postCommentParent->magazine->name.'/microblog');
        self::assertAnySelectorTextContains('.post .content p', 'parent');
        self::assertAnySelectorTextNotContains('.post .content p', 'invisible');
    }

    private function prepareContent(string $prefix): void
    {
        $this->user = $this->getUserByUsername($prefix.'-User');
        $poster = $this->getUserByUsername($prefix.'-Poster');
        $mag = $this->getMagazineByName($prefix);

        $tagVisible = $prefix.'_notblocked';
        $tagInvisible = $prefix.'_blocked';

        $entry = $this->createEntry('parent', $mag, $poster);
        $this->entryCommentParent = $this->createEntryComment('parent comment', $entry, $poster);
        $t = $this->createEntryComment('invisible 1 #'.$tagInvisible, $entry, $poster);
        $this->entityManager->refresh($t);
        $entryCommentSub1 = $this->createEntryComment('invisible 2 #'.$tagInvisible, $entry, $poster, $this->entryCommentParent);
        $t = $this->createEntryComment('visible 1 #'.$tagVisible, $entry, $poster);
        $this->entityManager->refresh($t);
        $entryCommentSub2 = $this->createEntryComment('visible 2 #'.$tagVisible, $entry, $poster, $this->entryCommentParent);
        $t = $this->createEntryComment('visible 3 #'.$tagVisible, $entry, $poster, $entryCommentSub1);
        $this->entityManager->refresh($t);
        $t = $this->createEntryComment('invisible 3 #'.$tagInvisible, $entry, $poster, $entryCommentSub2);
        $this->entityManager->refresh($t);
        $this->entityManager->refresh($entry);
        $this->entityManager->refresh($this->entryCommentParent);
        $this->entityManager->refresh($entryCommentSub1);
        $this->entityManager->refresh($entryCommentSub2);

        $post = $this->createPost('parent', $mag, $poster);
        $t = $this->postCommentParent = $this->createPostComment('parent comment', $post, $poster);
        $this->entityManager->refresh($t);
        $t = $this->createPostComment('invisible 1 #'.$tagInvisible, $post, $poster);
        $this->entityManager->refresh($t);
        $postCommentSub1 = $this->createPostComment('invisible 2 #'.$tagInvisible, $post, $poster, parent: $this->postCommentParent);
        $t = $this->createPostComment('visible 1 #'.$tagVisible, $post, $poster);
        $this->entityManager->refresh($t);
        $postCommentSub2 = $this->createPostComment('visible 2 #'.$tagVisible, $post, $poster, parent: $this->postCommentParent);
        $t = $this->createPostComment('visible 3 #'.$tagVisible, $post, $poster, parent: $postCommentSub1);
        $this->entityManager->refresh($t);
        $t = $this->createPostComment('invisible 3 #'.$tagInvisible, $post, $poster, parent: $postCommentSub2);
        $this->entityManager->refresh($t);
        $this->entityManager->refresh($post);
        $this->entityManager->refresh($this->postCommentParent);
        $this->entityManager->refresh($postCommentSub1);
        $this->entityManager->refresh($postCommentSub2);

        $this->tagManager->block($this->user, $this->tagRepository->findOneBy(['tag' => $tagInvisible]));
    }
}
