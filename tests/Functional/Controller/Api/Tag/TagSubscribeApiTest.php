<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Tag;

use App\Tests\WebTestCase;
use PHPUnit\Framework\Attributes\Group;

class TagSubscribeApiTest extends WebTestCase
{
    public function testApiCannotSubscribeHashtagAnonymous()
    {
        $this->getEntryByTitle('TagSubscribeApiTest', body: 'some text with #someTag');

        $this->client->request('PUT', '/api/tag/sometag/subscribe');
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotSubscribeHashtagWithoutScope()
    {
        $this->getEntryByTitle('TagSubscribeApiTest', body: 'some text with #someTag');

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/tag/sometag/subscribe', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    #[Group(name: 'NonThreadSafe')]
    public function testApiCanSubscribeHashtag()
    {
        $this->getEntryByTitle('TagSubscribeApiTest', body: 'some text with #someTag');

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read hashtag:subscribe');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/tag/sometag/subscribe', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::HASHTAG_RESPONSE_KEYS, $jsonData);
        self::assertEquals('sometag', $jsonData['tag']);
        self::assertSame(1, $jsonData['entryCount']);
        self::assertSame(0, $jsonData['entryCommentCount']);
        self::assertSame(0, $jsonData['postCount']);
        self::assertSame(0, $jsonData['postCommentCount']);
        self::assertNull($jsonData['isBlockedByUser']);
        self::assertTrue($jsonData['isSubscribedByUser']);

        // Idempotent when called multiple times
        $this->client->request('PUT', '/api/tag/sometag/subscribe', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::HASHTAG_RESPONSE_KEYS, $jsonData);
        self::assertEquals('sometag', $jsonData['tag']);
        self::assertSame(1, $jsonData['entryCount']);
        self::assertSame(0, $jsonData['entryCommentCount']);
        self::assertSame(0, $jsonData['postCount']);
        self::assertSame(0, $jsonData['postCommentCount']);
        self::assertNull($jsonData['isBlockedByUser']);
        self::assertTrue($jsonData['isSubscribedByUser']);
    }

    public function testApiCannotUnsubscribeHashtagAnonymous()
    {
        $this->getEntryByTitle('TagSubscribeApiTest', body: 'some text with #someTag');

        $this->client->request('PUT', '/api/tag/sometag/unsubscribe');
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotUnsubscribeHashtagWithoutScope()
    {
        $this->getEntryByTitle('TagSubscribeApiTest', body: 'some text with #someTag');

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/tag/sometag/unsubscribe', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    #[Group(name: 'NonThreadSafe')]
    public function testApiCanUnsubscribeHashtag()
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->getEntryByTitle('TagSubscribeApiTest', body: 'some text with #someTag');

        $this->tagManager->subscribe($user, $this->tagRepository->findOneBy(['tag' => 'sometag']));

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read hashtag:subscribe');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/tag/sometag/unsubscribe', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::HASHTAG_RESPONSE_KEYS, $jsonData);
        self::assertEquals('sometag', $jsonData['tag']);
        self::assertSame(1, $jsonData['entryCount']);
        self::assertSame(0, $jsonData['entryCommentCount']);
        self::assertSame(0, $jsonData['postCount']);
        self::assertSame(0, $jsonData['postCommentCount']);
        self::assertNull($jsonData['isBlockedByUser']);
        self::assertFalse($jsonData['isSubscribedByUser']);

        // Idempotent when called multiple times
        $this->client->request('PUT', '/api/tag/sometag/unsubscribe', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::HASHTAG_RESPONSE_KEYS, $jsonData);
        self::assertEquals('sometag', $jsonData['tag']);
        self::assertSame(1, $jsonData['entryCount']);
        self::assertSame(0, $jsonData['entryCommentCount']);
        self::assertSame(0, $jsonData['postCount']);
        self::assertSame(0, $jsonData['postCommentCount']);
        self::assertNull($jsonData['isBlockedByUser']);
        self::assertFalse($jsonData['isSubscribedByUser']);
    }

    public function testApiCannotRetrieveSubscribedHashtagsAnonymous()
    {
        $this->client->request('GET', '/api/tags/subscribed');
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotRetrieveSubscribedHashtagWithoutScope()
    {
        $this->getEntryByTitle('TagSubscribeApiTest', body: 'some text with #someTag');
        $user = $this->getUserByUsername('JohnDoe');
        $this->tagManager->subscribe($user, $this->tagRepository->findOneBy(['tag' => 'sometag']));

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/tags/subscribed', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanRetrieveSubscribedHashtags()
    {
        $this->getEntryByTitle('testApiCanRetrieveSubscribedHashtags', body: 'some text with #tag1 #tag2 #tag3');
        $user = $this->getUserByUsername('JohnDoe');

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read hashtag:subscribe');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/tag/tag1/subscribe', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $this->client->request('PUT', '/api/tag/tag2/subscribe', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/tags/subscribed', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);

        $subscribed = $jsonData['items'];
        self::assertIsArray($subscribed);
        self::assertCount(2, $subscribed);

        $tag1Found = false;
        $tag2Found = false;
        foreach ($subscribed as $sub) {
            self::assertIsArray($sub);
            self::assertArrayKeysMatch(self::HASHTAG_RESPONSE_KEYS, $sub);
            self::assertSame(1, $sub['entryCount']);
            self::assertSame(0, $sub['entryCommentCount']);
            self::assertSame(0, $sub['postCount']);
            self::assertSame(0, $sub['postCommentCount']);
            self::assertNull($sub['isBlockedByUser']);
            self::assertTrue($sub['isSubscribedByUser']);

            $tag1Found = ($tag1Found or 'tag1' === $sub['tag']);
            $tag2Found = ($tag2Found or 'tag2' === $sub['tag']);
        }
        self::assertTrue($tag1Found);
        self::assertTrue($tag2Found);
    }
}
