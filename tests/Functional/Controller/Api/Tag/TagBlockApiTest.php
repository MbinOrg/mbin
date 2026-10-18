<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Tag;

use App\Tests\WebTestCase;
use DateInterval;
use PHPUnit\Framework\Attributes\Group;

class TagBlockApiTest extends WebTestCase
{
    public function testApiCannotBlockHashtagAnonymous()
    {
        $this->getEntryByTitle('TagBlockApiTest', body: 'some text with #someTag');

        $this->client->request('PUT', '/api/tag/sometag/block');
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotBlockHashtagWithoutScope()
    {
        $this->getEntryByTitle('TagBlockApiTest', body: 'some text with #someTag');

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/tag/sometag/block', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    #[Group(name: 'NonThreadSafe')]
    public function testApiCanBlockHashtag()
    {
        $this->getEntryByTitle('TagBlockApiTest', body: 'some text with #someTag');

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read hashtag:block');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/tag/sometag/block', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::HASHTAG_RESPONSE_KEYS, $jsonData);
        self::assertEquals('sometag', $jsonData['tag']);
        self::assertSame(1, $jsonData['entryCount']);
        self::assertSame(0, $jsonData['entryCommentCount']);
        self::assertSame(0, $jsonData['postCount']);
        self::assertSame(0, $jsonData['postCommentCount']);
        self::assertNull($jsonData['isSubscribedByUser']);
        self::assertTrue($jsonData['isBlockedByUser']);

        // Idempotent when called multiple times
        $this->client->request('PUT', '/api/tag/sometag/block', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::HASHTAG_RESPONSE_KEYS, $jsonData);
        self::assertEquals('sometag', $jsonData['tag']);
        self::assertSame(1, $jsonData['entryCount']);
        self::assertSame(0, $jsonData['entryCommentCount']);
        self::assertSame(0, $jsonData['postCount']);
        self::assertSame(0, $jsonData['postCommentCount']);
        self::assertNull($jsonData['isSubscribedByUser']);
        self::assertTrue($jsonData['isBlockedByUser']);
    }

    public function testApiCannotUnblockHashtagAnonymous()
    {
        $this->getEntryByTitle('TagBlockApiTest', body: 'some text with #someTag');

        $this->client->request('PUT', '/api/tag/sometag/unblock');
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotUnblockHashtagWithoutScope()
    {
        $this->getEntryByTitle('TagBlockApiTest', body: 'some text with #someTag');

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/tag/sometag/unblock', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    #[Group(name: 'NonThreadSafe')]
    public function testApiCanUnblockHashtag()
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->getEntryByTitle('TagBlockApiTest', body: 'some text with #someTag');

        $this->tagManager->block($user, $this->tagRepository->findOneBy(['tag' => 'sometag']));

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read hashtag:block');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/tag/sometag/unblock', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::HASHTAG_RESPONSE_KEYS, $jsonData);
        self::assertEquals('sometag', $jsonData['tag']);
        self::assertSame(1, $jsonData['entryCount']);
        self::assertSame(0, $jsonData['entryCommentCount']);
        self::assertSame(0, $jsonData['postCount']);
        self::assertSame(0, $jsonData['postCommentCount']);
        self::assertNull($jsonData['isSubscribedByUser']);
        self::assertFalse($jsonData['isBlockedByUser']);

        // Idempotent when called multiple times
        $this->client->request('PUT', '/api/tag/sometag/unblock', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::HASHTAG_RESPONSE_KEYS, $jsonData);
        self::assertEquals('sometag', $jsonData['tag']);
        self::assertSame(1, $jsonData['entryCount']);
        self::assertSame(0, $jsonData['entryCommentCount']);
        self::assertSame(0, $jsonData['postCount']);
        self::assertSame(0, $jsonData['postCommentCount']);
        self::assertNull($jsonData['isSubscribedByUser']);
        self::assertFalse($jsonData['isBlockedByUser']);
    }

    public function testApiCannotRetrieveBlockedHashtagsAnonymous()
    {
        $this->client->request('GET', '/api/tags/blocked');
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotRetrieveBlockedHashtagWithoutScope()
    {
        $this->getEntryByTitle('TagBlockApiTest', body: 'some text with #someTag');
        $user = $this->getUserByUsername('JohnDoe');
        $this->tagManager->block($user, $this->tagRepository->findOneBy(['tag' => 'sometag']));

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/tags/blocked', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanRetrieveBlockedHashtags()
    {
        $this->getEntryByTitle('testApiCanRetrieveBlockedHashtags', body: 'some text with #tag1 #tag2 #tag3');
        $user = $this->getUserByUsername('JohnDoe');

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read hashtag:block');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/tag/tag1/block', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $this->client->request('PUT', '/api/tag/tag2/block', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/tags/blocked', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);

        $blocked = $jsonData['items'];
        self::assertIsArray($blocked);
        self::assertCount(2, $blocked);

        $tag1Found = false;
        $tag2Found = false;
        foreach ($blocked as $block) {
            self::assertIsArray($block);
            self::assertArrayKeysMatch(self::HASHTAG_RESPONSE_KEYS, $block);
            self::assertSame(1, $block['entryCount']);
            self::assertSame(0, $block['entryCommentCount']);
            self::assertSame(0, $block['postCount']);
            self::assertSame(0, $block['postCommentCount']);
            self::assertNull($block['isSubscribedByUser']);
            self::assertTrue($block['isBlockedByUser']);

            $tag1Found = ($tag1Found or 'tag1' === $block['tag']);
            $tag2Found = ($tag2Found or 'tag2' === $block['tag']);
        }
        self::assertTrue($tag1Found);
        self::assertTrue($tag2Found);
    }

    public function testApiBlocksInUserEntries()
    {
        $prefix = 'testApiBlocksInUserEntries';
        $poster = $this->getUserByUsername('Poster');
        $mag = $this->getMagazineByName($prefix);
        $this->createEntry('invisible 1', $mag, $poster, body: "entry with #{$prefix}_blocked");
        $this->createEntry('invisible 2', $mag, $poster, body: "entry with #{$prefix}_blocked");
        $entryVisible1 = $this->createEntry('visible 1', $mag, $poster, body: "entry with #{$prefix}_notblocked");
        $entryVisible2 = $this->createEntry('visible 2', $mag, $poster, body: "entry with #{$prefix}_notblocked");
        $this->setContentTime($entryVisible2, $entryVisible1, 2);

        $user = $this->getUserByUsername('JohnDoe');
        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read hashtag:block');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/tag/{$prefix}_blocked/block", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $this->client->request(
            'GET', '/api/users/'.$poster->getId().'/entries?sort=newest',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseIsSuccessful();
        $items = self::getJsonResponse($this->client)['items'];

        self::assertCount(2, $items);
        self::assertSame($entryVisible2->getId(), $items[0]['entryId']);
        self::assertSame($entryVisible1->getId(), $items[1]['entryId']);
    }

    public function testApiBlocksInUserPosts()
    {
        $prefix = 'testApiBlocksInUserPosts';
        $poster = $this->getUserByUsername('Poster');
        $mag = $this->getMagazineByName($prefix);
        $this->createPost("invisible 1 #{$prefix}_blocked", $mag, $poster);
        $this->createPost("invisible 2 #{$prefix}_blocked", $mag, $poster);
        $postVisible1 = $this->createPost("visible 1 #{$prefix}_notblocked", $mag, $poster);
        $postVisible2 = $this->createPost("visible 2 #{$prefix}_notblocked", $mag, $poster);
        $this->setContentTime($postVisible2, $postVisible1, 2);

        $user = $this->getUserByUsername('JohnDoe');
        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read hashtag:block');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/tag/{$prefix}_blocked/block", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $this->client->request(
            'GET', '/api/users/'.$poster->getId().'/posts?sort=newest',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseIsSuccessful();
        $items = self::getJsonResponse($this->client)['items'];

        self::assertCount(2, $items);
        self::assertSame($postVisible2->getId(), $items[0]['postId']);
        self::assertSame($postVisible1->getId(), $items[1]['postId']);
    }

    public function testApiBlocksInUserEntryComments()
    {
        $prefix = 'testApiBlocksInUserEntryComments';
        $poster = $this->getUserByUsername('Poster');
        $mag = $this->getMagazineByName($prefix);
        $entry = $this->createEntry('parent', $mag, $poster);
        $comment = $this->createEntryComment("parent comment", $entry, $poster);
        $this->createEntryComment("invisible 1 #{$prefix}_blocked", $entry, $poster);
        $this->createEntryComment("invisible 2 #{$prefix}_blocked", $entry, $poster, $comment);
        $commentVisible1 = $this->createEntryComment("visible 1 #{$prefix}_notblocked", $entry, $poster);
        $commentVisible2 = $this->createEntryComment("visible 2 #{$prefix}_notblocked", $entry, $poster, $comment);
        $this->setContentTime($commentVisible1, $comment, 2);
        $this->setContentTime($commentVisible2, $comment, 4);

        $user = $this->getUserByUsername('JohnDoe');
        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read hashtag:block');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/tag/{$prefix}_blocked/block", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $this->client->request(
            'GET', '/api/users/'.$poster->getId().'/comments?sort=newest',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseIsSuccessful();
        $items = self::getJsonResponse($this->client)['items'];

        self::assertCount(3, $items);
        self::assertSame($commentVisible2->getId(), $items[0]['commentId']);
        self::assertSame($commentVisible1->getId(), $items[1]['commentId']);
        self::assertSame($comment->getId(), $items[2]['commentId']);
    }

    public function testApiBlocksInUserPostComments()
    {
        $prefix = 'testApiBlocksInUserPostComments';
        $poster = $this->getUserByUsername('Poster');
        $mag = $this->getMagazineByName($prefix);
        $post = $this->createPost('parent', $mag, $poster);
        $comment = $this->createPostComment("parent comment", $post, $poster);
        $this->createPostComment("invisible 1 #{$prefix}_blocked", $post, $poster);
        $this->createPostComment("invisible 2 #{$prefix}_blocked", $post, $poster, parent: $comment);
        $commentVisible1 = $this->createPostComment("visible 1 #{$prefix}_notblocked", $post, $poster);
        $commentVisible2 = $this->createPostComment("visible 2 #{$prefix}_notblocked", $post, $poster, parent: $comment);
        $this->setContentTime($commentVisible1, $comment, 2);
        $this->setContentTime($commentVisible2, $comment, 4);

        $user = $this->getUserByUsername('JohnDoe');
        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read hashtag:block');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/tag/{$prefix}_blocked/block", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $this->client->request(
            'GET', '/api/users/'.$poster->getId().'/post-comments?sort=newest',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseIsSuccessful();
        $items = self::getJsonResponse($this->client)['items'];

        self::assertCount(3, $items);
        self::assertSame($commentVisible2->getId(), $items[0]['commentId']);
        self::assertSame($commentVisible1->getId(), $items[1]['commentId']);
        self::assertSame($comment->getId(), $items[2]['commentId']);
    }
}
