<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Post;

use App\Tests\WebTestCase;

class PostDeleteControllerTest extends WebTestCase
{
    public function testUserCanDeletePost()
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByName('acme');
        $post = $this->createPost('deletion test', magazine: $magazine, user: $user);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}/deletion-test");

        $this->assertSelectorExists('form[action$="delete"]');
        $this->client->submit(
            $crawler->filter('form[action$="delete"]')->selectButton('Delete')->form()
        );

        $this->assertResponseRedirects();
    }

    public function testUserCanSoftDeletePost()
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByName('acme');
        $post = $this->createPost('deletion test', magazine: $magazine, user: $user);
        $comment = $this->createPostComment('really?', $post, $user);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}/deletion-test");

        $this->assertSelectorExists("#post-{$post->getId()} form[action$=\"delete\"]");
        $this->client->submit(
            $crawler->filter("#post-{$post->getId()} form[action$=\"delete\"]")->selectButton('Delete')->form()
        );

        $this->assertResponseRedirects();
        $this->client->request('GET', "/m/acme/p/{$post->getId()}/deletion-test");
        $translator = $this->translator;
        $this->assertSelectorTextContains("#post-{$post->getId()} .content", $translator->trans('deleted_by_author'));
    }
}
