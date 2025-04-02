<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Post;

use App\Entity\Contracts\VotableInterface;
use App\Enums\ESortOptions;
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

    public function testCommentsDefaultSortOption(): void
    {
        $user = $this->getUserByUsername('user');
        $post = $this->createPost('entry');
        $older = $this->createPostComment('older comment', post: $post);
        $older->createdAt = new \DateTimeImmutable('now - 1 day');
        $newer = $this->createPostComment('newer comment', post: $post);

        $user->commentDefaultSort = ESortOptions::Oldest->value;
        $this->entityManager->flush();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', "/m/{$post->magazine->name}/p/{$post->getId()}/-");
        self::assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.options__main .active', $this->translator->trans(ESortOptions::Oldest->value));

        $iterator = $crawler->filter('#comments div')->children()->getIterator();
        /** @var \DOMElement $firstNode */
        $firstNode = $iterator->current();
        $firstId = $firstNode->attributes->getNamedItem('id')->nodeValue;
        self::assertEquals("post-comment-{$older->getId()}", $firstId);
        $iterator->next();
        $secondNode = $iterator->current();
        $secondId = $secondNode->attributes->getNamedItem('id')->nodeValue;
        self::assertEquals("post-comment-{$newer->getId()}", $secondId);

        $user->commentDefaultSort = ESortOptions::Newest->value;
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', "/m/{$post->magazine->name}/p/{$post->getId()}/-");
        self::assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.options__main .active', $this->translator->trans(ESortOptions::Newest->value));

        $iterator = $crawler->filter('#comments div')->children()->getIterator();
        /** @var \DOMElement $firstNode */
        $firstNode = $iterator->current();
        $firstId = $firstNode->attributes->getNamedItem('id')->nodeValue;
        self::assertEquals("post-comment-{$newer->getId()}", $firstId);
        $iterator->next();
        $secondNode = $iterator->current();
        $secondId = $secondNode->attributes->getNamedItem('id')->nodeValue;
        self::assertEquals("post-comment-{$older->getId()}", $secondId);
    }
}
