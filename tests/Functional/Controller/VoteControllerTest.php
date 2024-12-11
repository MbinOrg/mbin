<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\WebTestCase;

class VoteControllerTest extends WebTestCase
{
    public function testUserCanVoteOnEntry(): void
    {
        $this->client->loginUser($this->getUserByUsername('Actor'));

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $u1 = $this->getUserByUsername('JohnDoe');
        $u2 = $this->getUserByUsername('JaneDoe');

        $this->createVote(1, $entry, $u1);
        $this->createVote(1, $entry, $u2);

        $this->client->request('GET', '/');
        $crawler = $this->client->request('GET', '/m/acme/t/'.$entry->getId().'/-/comments');

        $this->assertUpDownVoteActions($crawler);
    }

    public function testXmlUserCanVoteOnEntry(): void
    {
        $this->client->loginUser($this->getUserByUsername('Actor'));

        $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $crawler = $this->client->request('GET', '/');
        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->click($crawler->filter('.entry .vote__up')->form());

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
    }

    public function testUserCanVoteOnEntryComment(): void
    {
        $this->client->loginUser($this->getUserByUsername('Actor'));

        $comment = $this->createEntryComment('test entry comment 1');

        $u1 = $this->getUserByUsername('JohnDoe');
        $u2 = $this->getUserByUsername('JaneDoe');

        $this->createVote(1, $comment, $u1);
        $this->createVote(1, $comment, $u2);

        $this->client->request('GET', '/');
        $crawler = $this->client->request('GET', '/m/acme/t/'.$comment->entry->getId().'/-/comments');

        $this->assertUpDownVoteActions($crawler, '.comment');
    }

    public function testXmlUserCanVoteOnEntryComment(): void
    {
        $this->client->loginUser($this->getUserByUsername('Actor'));

        $comment = $this->createEntryComment('test entry comment 1');

        $crawler = $this->client->request('GET', '/m/acme/t/'.$comment->entry->getId().'/-/comments');
        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->click($crawler->filter('.entry-comment .vote__up')->form());

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
    }

    private function assertUpDownVoteActions($crawler, string $selector = ''): void
    {
        $this->assertSelectorTextContains($selector.' .vote__up', '2');
        $this->assertSelectorTextContains($selector.' .vote__down', '0');

        $this->client->click($crawler->filter($selector.' .vote__up')->form());
        $crawler = $this->client->followRedirect();

        $this->assertSelectorTextContains($selector.' .vote__up', '3');
        $this->assertSelectorTextContains($selector.' .vote__down', '0');

        $this->client->click($crawler->filter($selector.' .vote__down')->form());
        $crawler = $this->client->followRedirect();

        $this->assertSelectorTextContains($selector.' .vote__up', '2');
        $this->assertSelectorTextContains($selector.' .vote__down', '1');

        $this->client->click($crawler->filter($selector.' .vote__down')->form());
        $crawler = $this->client->followRedirect();

        $this->assertSelectorTextContains($selector.' .vote__up', '2');
        $this->assertSelectorTextContains($selector.' .vote__down', '0');

        $this->client->submit($crawler->filter($selector.' .vote__up')->form());
        $crawler = $this->client->followRedirect();

        $this->assertSelectorTextContains($selector.' .vote__up', '3');
        $this->assertSelectorTextContains($selector.' .vote__down', '0');

        $this->client->submit($crawler->filter($selector.' .vote__up')->form());
        $this->client->followRedirect();

        $this->assertSelectorTextContains($selector.' .vote__up', '2');
        $this->assertSelectorTextContains($selector.' .vote__down', '0');
    }

    public function testUserCanVoteOnPost(): void
    {
        $this->client->loginUser($this->getUserByUsername('Actor'));

        $post = $this->createPost('test post 1');

        $u1 = $this->getUserByUsername('JohnDoe');
        $u2 = $this->getUserByUsername('JaneDoe');

        $this->createVote(1, $post, $u1);
        $this->createVote(1, $post, $u2);

        $crawler = $this->client->request('GET', '/m/acme/p/'.$post->getId().'/-');
        self::assertResponseIsSuccessful();

        $this->assertUpVoteActions($crawler);
    }

    public function testXmlUserCanVoteOnPost(): void
    {
        $this->client->loginUser($this->getUserByUsername('Actor'));

        $this->createPost('test post 1');

        $crawler = $this->client->request('GET', '/microblog');
        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->click($crawler->filter('.post .vote__up')->form());

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
    }

    public function testUserCanVoteOnPostComment(): void
    {
        $this->client->loginUser($this->getUserByUsername('Actor'));

        $comment = $this->createPostComment('test post comment 1');

        $u1 = $this->getUserByUsername('JohnDoe');
        $u2 = $this->getUserByUsername('JaneDoe');

        $this->createVote(1, $comment, $u1);
        $this->createVote(1, $comment, $u2);

        $crawler = $this->client->request('GET', '/m/acme/p/'.$comment->post->getId());

        $this->assertUpVoteActions($crawler, '.comment');
    }

    public function testXmlUserCanVoteOnPostComment(): void
    {
        $this->client->loginUser($this->getUserByUsername('Actor'));

        $comment = $this->createPostComment('test post comment 1');

        $crawler = $this->client->request('GET', '/m/acme/p/'.$comment->post->getId().'/-');
        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->click($crawler->filter('.post-comment .vote__up')->form());

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
    }

    private function assertUpVoteActions($crawler, string $selector = ''): void
    {
        $this->assertSelectorTextContains($selector.' .vote__up', '2');

        $this->client->submit($crawler->filter($selector.' .vote__up')->form());
        $crawler = $this->client->followRedirect();

        $this->assertSelectorTextContains($selector.' .vote__up', '3');

        $this->client->submit($crawler->filter($selector.' .vote__up')->form());
        $this->client->followRedirect();

        $this->assertSelectorTextContains($selector.' .vote__up', '2');
    }
}
