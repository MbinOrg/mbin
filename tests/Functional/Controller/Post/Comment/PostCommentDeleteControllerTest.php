<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Post\Comment;

use App\Tests\WebTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class PostCommentDeleteControllerTest extends WebTestCase
{
    public function testUserCannotPurgePostComment()
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByName('acme', $user);
        $post = $this->createPost('deletion test', magazine: $magazine, user: $user);
        $comment = $this->createPostComment('delete me!', $post, $user);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}/deletion-test");
        self::assertResponseIsSuccessful();

        $link = $crawler->filter('#comments .post-comment footer')->selectLink('Moderate')->link();
        $crawler = $this->client->click($link);
        self::assertResponseIsSuccessful();

        $this->assertSelectorNotExists('.moderate-panel form[action$="purge"]');
    }

    public function testAdminCanPurgePostComment()
    {
        $user = $this->getUserByUsername('user');
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $magazine = $this->getMagazineByName('acme', $user);
        $post = $this->createPost('deletion test', magazine: $magazine, user: $user);
        $comment = $this->createPostComment('delete me!', $post, $user);
        $this->client->loginUser($admin);
        self::assertTrue($admin->isAdmin());

        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}/deletion-test");
        self::assertResponseIsSuccessful();

        $link = $crawler->filter("#comments #post-comment-{$comment->getId()} footer")->selectLink('Moderate')->link();
        $crawler = $this->client->click($link);
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.moderate-panel');
        $this->assertSelectorExists('.moderate-panel form[action$="purge"]');
        $this->client->submit(
            $crawler->filter('.moderate-panel')->selectButton('Purge')->form()
        );

        $this->assertResponseRedirects();
    }

    public function testUserCanSoftDeletePostComment()
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByName('acme', $user);
        $post = $this->createPost('deletion test', magazine: $magazine, user: $user);
        $comment = $this->createPostComment('delete me!', $post, $user);
        $reply = $this->createPostCommentReply('Are you deleted yet?', $post, $user, $comment);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}/deletion-test");
        self::assertResponseIsSuccessful();

        $link = $crawler->filter('#comments .post-comment footer')->selectLink('Moderate')->link();
        $crawler = $this->client->click($link);
        self::assertResponseIsSuccessful();

        $this->assertSelectorExists('.moderate-panel form[action$="delete"]');
        $this->client->submit(
            $crawler->filter('.moderate-panel')->selectButton('Delete')->form()
        );

        $this->assertResponseRedirects();
        $this->client->request('GET', "/m/acme/p/{$post->getId()}/deletion-test");

        $translator = $this->getService(TranslatorInterface::class);
        $this->assertSelectorTextContains("#post-comment-{$comment->getId()} .content", $translator->trans('deleted_by_author'));
    }
}
