<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Post;

use App\Entity\Contracts\VotableInterface;
use App\Service\FavouriteManager;
use App\Service\VoteManager;
use App\Tests\WebTestCase;

class PostSingleControllerTest extends WebTestCase
{
    public function testUserCanGoToPostFromFrontpage(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $this->createPost('test post 1');

        $crawler = $this->client->request('GET', '/microblog');
        $this->client->click($crawler->filter('.link-muted')->link());

        $this->assertSelectorTextContains('blockquote', 'test post 1');
        $this->assertSelectorTextContains('#main', 'No comments');
        $this->assertSelectorTextContains('#sidebar .magazine', 'Magazine');
        $this->assertSelectorTextContains('#sidebar .user-list', 'Moderators');
    }

    public function testUserCanSeePost(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $post = $this->createPost('test post 1');

        $this->client->request('GET', "/m/acme/p/{$post->getId()}/test-post-1");

        $this->assertSelectorTextContains('blockquote', 'test post 1');
    }

    public function testPostActivityCounter(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $post = $this->createPost('test post 1');

        $manager = $this->client->getContainer()->get(VoteManager::class);
        $manager->vote(VotableInterface::VOTE_DOWN, $post, $this->getUserByUsername('JaneDoe'));

        $manager = $this->client->getContainer()->get(FavouriteManager::class);
        $manager->toggle($this->getUserByUsername('JohnDoe'), $post);
        $manager->toggle($this->getUserByUsername('JaneDoe'), $post);

        $this->client->request('GET', "/m/acme/p/{$post->getId()}/test-post-1");

        $this->assertSelectorTextContains('.options-activity', 'Activity (2)');
    }
}
