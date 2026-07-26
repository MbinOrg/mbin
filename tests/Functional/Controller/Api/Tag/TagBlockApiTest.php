<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Tag;

use App\Tests\WebTestCase;
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
}
