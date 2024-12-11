<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Repository\ReportRepository;
use App\Tests\WebTestCase;

class ReportControllerControllerTest extends WebTestCase
{
    public function testLoggedUserCanReportEntry(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $entry = $this->getEntryByTitle(
            'test entry 1',
            'https://kbin.pub',
            null,
            null,
            $this->getUserByUsername('JaneDoe')
        );

        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/test-entry-1");
        $crawler = $this->client->click($crawler->filter('#main .entry menu')->selectLink('Report')->link());

        $this->assertSelectorExists('#main .entry');

        $this->client->submit(
            $crawler->filter('form[name=report]')->selectButton('Report')->form(
                [
                    'report[reason]' => 'test reason 1',
                ]
            )
        );

        $repo = $this->getService(ReportRepository::class);

        $this->assertEquals(1, $repo->count([]));
    }

    public function testLoggedUserCanReportEntryComment(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $entry = $this->getEntryByTitle(
            'test entry 1',
            'https://kbin.pub',
            null,
            null,
            $this->getUserByUsername('JaneDoe')
        );
        $this->createEntryComment('test comment 1', $entry, $this->getUserByUsername('JaneDoe'));

        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/test-entry-1");
        $crawler = $this->client->click($crawler->filter('#main .entry-comment')->selectLink('Report')->link());

        $this->assertSelectorExists('#main .entry-comment');

        $this->client->submit(
            $crawler->filter('form[name=report]')->selectButton('Report')->form(
                [
                    'report[reason]' => 'test reason 1',
                ]
            )
        );

        $repo = $this->getService(ReportRepository::class);

        $this->assertEquals(1, $repo->count([]));
    }

    public function testLoggedUserCanReportPost(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $post = $this->createPost('test post 1', null, $this->getUserByUsername('JaneDoe'));

        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}/test-post-1");
        $crawler = $this->client->click($crawler->filter('#main .post menu')->selectLink('Report')->link());

        $this->assertSelectorExists('#main .post');

        $this->client->submit(
            $crawler->filter('form[name=report]')->selectButton('Report')->form(
                [
                    'report[reason]' => 'test reason 1',
                ]
            )
        );

        $repo = $this->getService(ReportRepository::class);

        $this->assertEquals(1, $repo->count([]));
    }

    public function testLoggedUserCanReportPostComment(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $post = $this->createPost('test post 1', null, $this->getUserByUsername('JaneDoe'));
        $this->createPostComment('test comment 1', $post, $this->getUserByUsername('JaneDoe'));

        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}/test-post-1");
        $crawler = $this->client->click($crawler->filter('#main .post-comment menu')->selectLink('Report')->link());

        $this->assertSelectorExists('#main .post-comment');

        $this->client->submit(
            $crawler->filter('form[name=report]')->selectButton('Report')->form(
                [
                    'report[reason]' => 'test reason 1',
                ]
            )
        );

        $repo = $this->getService(ReportRepository::class);

        $this->assertEquals(1, $repo->count([]));
    }
}
