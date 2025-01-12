<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Search;

use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPub\ApHttpClientInterface;
use App\Service\SettingsManager;
use App\Tests\WebTestCase;
use phpseclib3\Crypt\RSA;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class SearchApiTest extends WebTestCase
{
    public const SEARCH_PAGINATED_KEYS = ['items', 'pagination', 'apActors', 'apObjects'];
    public const SEARCH_AP_ACTOR_KEYS = ['type', 'object'];

    private RSA\PrivateKey $key;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->key = RSA::createKey(1024);
    }

    public function testApiCannotSearchWithNoQuery(): void
    {
        $this->client->request('GET', '/api/search');

        self::assertResponseStatusCodeSame(400);
    }

    public function testApiCanFindEntryByTitleAnonymous(): void
    {
        $entry = $this->getEntryByTitle('A test title to search for');
        $this->getEntryByTitle('Cannot find this');

        $this->client->request('GET', '/api/search?q=title');

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::SEARCH_PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertIsArray($jsonData['apActors']);
        self::assertEmpty($jsonData['apActors']);
        self::assertIsArray($jsonData['apObjects']);
        self::assertEmpty($jsonData['apObjects']);

        self::assertIsArray($jsonData['items'][0]);
        self::assertArrayKeysMatch(array_merge(['itemType'], self::ENTRY_RESPONSE_KEYS), $jsonData['items'][0]);
        self::assertSame('entry', $jsonData['items'][0]['itemType']);
        self::assertSame($entry->getId(), $jsonData['items'][0]['entryId']);
    }

    public function testApiCanFindContentByBodyAnonymous(): void
    {
        $entry = $this->getEntryByTitle('A test title to search for', body: 'This is the body we\'re finding');
        $this->getEntryByTitle('Cannot find this', body: 'No keywords here!');
        $post = $this->createPost('Lets get a post with its body in there too!');
        $this->createPost('But not this one.');

        $this->client->request('GET', '/api/search?q=body');

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::SEARCH_PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['items']);
        self::assertCount(2, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertIsArray($jsonData['apActors']);
        self::assertEmpty($jsonData['apActors']);
        self::assertIsArray($jsonData['apObjects']);
        self::assertEmpty($jsonData['apObjects']);

        foreach ($jsonData['items'] as $item) {
            self::assertIsArray($item);
            self::assertArrayHasKey('itemType', $item);
            switch ($item['itemType']) {
                case 'entry':
                    self::assertArrayKeysMatch(array_merge(['itemType'], self::ENTRY_RESPONSE_KEYS), $item);
                    self::assertSame($entry->getId(), $item['entryId']);
                    break;
                case 'entry_comment':
                    self::assertNotReached('No entry_comment should have been found');
                    break;
                case 'post':
                    self::assertArrayKeysMatch(array_merge(['itemType'], self::POST_RESPONSE_KEYS), $item);
                    self::assertSame($post->getId(), $item['postId']);
                    break;
                case 'post_comment':
                    self::assertNotReached('No post_comment should have been found');
                    break;
                default:
                    self::assertNotReached();
                    break;
            }
        }
    }

    public function testApiCanFindCommentsByBodyAnonymous(): void
    {
        $entry = $this->getEntryByTitle('Cannot find this', body: 'No keywords here!');
        $post = $this->createPost('But not this one.');
        $entryComment = $this->createEntryComment('Some comment on a thread', $entry);
        $postComment = $this->createPostComment('Some comment on a post', $post);

        $this->client->request('GET', '/api/search?q=comment');

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::SEARCH_PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['items']);
        self::assertCount(2, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertIsArray($jsonData['apActors']);
        self::assertEmpty($jsonData['apActors']);
        self::assertIsArray($jsonData['apObjects']);
        self::assertEmpty($jsonData['apObjects']);

        foreach ($jsonData['items'] as $item) {
            self::assertIsArray($item);
            self::assertArrayHasKey('itemType', $item);
            switch ($item['itemType']) {
                case 'entry':
                    self::assertNotReached('No entry should have been found');
                    break;
                case 'entry_comment':
                    self::assertArrayKeysMatch(array_merge(['itemType'], self::ENTRY_COMMENT_RESPONSE_KEYS), $item);
                    self::assertSame($entryComment->getId(), $item['commentId']);
                    break;
                case 'post':
                    self::assertNotReached('No post should have been found');
                    break;
                case 'post_comment':
                    self::assertArrayKeysMatch(array_merge(['itemType'], self::POST_COMMENT_RESPONSE_KEYS), $item);
                    self::assertSame($postComment->getId(), $item['commentId']);
                    break;
                default:
                    self::assertNotReached();
                    break;
            }
        }
    }

    public function testApiCannotFindRemoteUserAnonymousWhenOptionSet(): void
    {
        $settingsManager = $this->settingsManager;
        $value = $settingsManager->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN');
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', true);

        $this->client->request('GET', '/api/search?q=ernest@kbin.social');

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::SEARCH_PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['items']);
        self::assertEmpty($jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertIsArray($jsonData['apActors']);
        self::assertEmpty($jsonData['apActors']);
        self::assertIsArray($jsonData['apObjects']);
        self::assertEmpty($jsonData['apObjects']);

        // Seems like settings can persist in the test environment? Might only be for bare metal setups
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', $value);
    }

    public function testApiCannotFindRemoteMagazineAnonymousWhenOptionSet(): void
    {
        $settingsManager = $this->settingsManager;
        $value = $settingsManager->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN');
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', true);

        $this->client->request('GET', '/api/search?q=kbinMeta@kbin.social');

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::SEARCH_PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['items']);
        self::assertEmpty($jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertIsArray($jsonData['apActors']);
        self::assertEmpty($jsonData['apActors']);
        self::assertIsArray($jsonData['apObjects']);
        self::assertEmpty($jsonData['apObjects']);

        // Seems like settings can persist in the test environment? Might only be for bare metal setups
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', $value);
    }

    /*
     * These tests do work, but we should not do requests to a remote server when running tests
    public function testApiCanFindRemoteUserAnonymousWhenOptionUnset(): void
    {
        $settingsManager = $this->settingsManager;
        $value = $settingsManager->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN');
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', false);
        $domain = $settingsManager->get('KBIN_DOMAIN');
        $this->getUserByUsername('test');
        $this->setCacheKeysForApHttpClient($domain);

        $this->client->request('GET', "/api/search?q=@eugen@mastodon.social");

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::SEARCH_PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['items']);
        self::assertEmpty($jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertIsArray($jsonData['apActors']);
        self::assertCount(1, $jsonData['apActors']);
        self::assertIsArray($jsonData['apObjects']);
        self::assertEmpty($jsonData['apObjects']);

        self::assertIsArray($jsonData['apActors'][0]);
        self::assertArrayKeysMatch(self::SEARCH_AP_ACTOR_KEYS, $jsonData['apActors'][0]);
        self::assertSame('user', $jsonData['apActors'][0]['type']);
        self::assertIsArray($jsonData['apActors'][0]['object']);
        self::assertArrayKeysMatch(self::USER_RESPONSE_KEYS, $jsonData['apActors'][0]['object']);
        self::assertSame('eugen@mastodon.social', $jsonData['apActors'][0]['object']['apId']);

        // Seems like settings can persist in the test environment? Might only be for bare metal setups
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', $value);
    }

    public function testApiCanFindRemoteMagazineAnonymousWhenOptionUnset(): void
    {        // Admin user must exist to retrieve a remote magazine since remote mods aren't federated (yet)
        $this->getUserByUsername('admin', isAdmin: true);

        $settingsManager = $this->settingsManager;
        $value = $settingsManager->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN');
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', false);
        $domain = $settingsManager->get('KBIN_DOMAIN');
        $logger = $this->loggerInterface;
        $this->setCacheKeysForApHttpClient($domain, $logger);
        $this->getMagazineByName('testMag');

        $this->client->request('GET', "/api/search?q=!technology@lemmy.world");

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::SEARCH_PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['items']);
        self::assertEmpty($jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertIsArray($jsonData['apActors']);
        self::assertCount(1, $jsonData['apActors']);
        self::assertIsArray($jsonData['apObjects']);
        self::assertEmpty($jsonData['apObjects']);

        self::assertIsArray($jsonData['apActors'][0]);
        self::assertArrayKeysMatch(self::SEARCH_AP_ACTOR_KEYS, $jsonData['apActors'][0]);
        self::assertSame('magazine', $jsonData['apActors'][0]['type']);
        self::assertIsArray($jsonData['apActors'][0]['object']);
        self::assertArrayKeysMatch(self::MAGAZINE_RESPONSE_KEYS, $jsonData['apActors'][0]['object']);
        self::assertSame('technology@lemmy.world', $jsonData['apActors'][0]['object']['apId']);

        // Seems like settings can persist in the test environment? Might only be for bare metal setups
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', $value);
    }

    public function testApiCanFindRemoteUser(): void
    {
        $settingsManager = $this->settingsManager;
        $value = $settingsManager->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN');
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', true);
        $domain = $settingsManager->get('KBIN_DOMAIN');
        $this->setCacheKeysForApHttpClient($domain);
        $this->getUserByUsername('test');

        $this->client->loginUser($this->getUserByUsername('user'));

        $this->client->request('GET', "/api/search?q=@eugen@mastodon.social");

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::SEARCH_PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['items']);
        self::assertEmpty($jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertIsArray($jsonData['apActors']);
        self::assertCount(1, $jsonData['apActors']);
        self::assertIsArray($jsonData['apObjects']);
        self::assertEmpty($jsonData['apObjects']);

        self::assertIsArray($jsonData['apActors'][0]);
        self::assertArrayKeysMatch(self::SEARCH_AP_ACTOR_KEYS, $jsonData['apActors'][0]);
        self::assertSame('user', $jsonData['apActors'][0]['type']);
        self::assertIsArray($jsonData['apActors'][0]['object']);
        self::assertArrayKeysMatch(self::USER_RESPONSE_KEYS, $jsonData['apActors'][0]['object']);
        self::assertSame('eugen@mastodon.social', $jsonData['apActors'][0]['object']['apId']);

        // Seems like settings can persist in the test environment? Might only be for bare metal setups
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', $value);
    }

    public function testApiCanFindRemoteMagazine(): void
    {
        $this->getUserByUsername('admin', isAdmin: true);

        $settingsManager = $this->settingsManager;
        $value = $settingsManager->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN');
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', true);
        $domain = $settingsManager->get('KBIN_DOMAIN');
        $this->setCacheKeysForApHttpClient($domain);

        $this->client->loginUser($this->getUserByUsername('user'));
        $this->getMagazineByName('testMag');

        $this->client->request('GET', "/api/search?q=!technology@lemmy.world");

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::SEARCH_PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['items']);
        self::assertEmpty($jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertIsArray($jsonData['apActors']);
        self::assertCount(1, $jsonData['apActors']);
        self::assertIsArray($jsonData['apObjects']);
        self::assertEmpty($jsonData['apObjects']);

        self::assertIsArray($jsonData['apActors'][0]);
        self::assertArrayKeysMatch(self::SEARCH_AP_ACTOR_KEYS, $jsonData['apActors'][0]);
        self::assertSame('magazine', $jsonData['apActors'][0]['type']);
        self::assertIsArray($jsonData['apActors'][0]['object']);
        self::assertArrayKeysMatch(self::MAGAZINE_RESPONSE_KEYS, $jsonData['apActors'][0]['object']);
        self::assertSame('technology@lemmy.world', $jsonData['apActors'][0]['object']['apId']);

        // Seems like settings can persist in the test environment? Might only be for bare metal setups
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', $value);
    }
     */

    private function setCacheKeysForApHttpClient(string $domain, ?LoggerInterface $logger = null): void
    {
        $cache = new ArrayAdapter();

        $key = $this->key;

        // Set 'fake' keys in cache for testing purposes
        $cache->get('instance_private_key', function (ItemInterface $item) use ($key) {
            $item->expiresAt(new \DateTime('+1 day'));

            return (string) $key;
        });
        $cache->get('instance_public_key', function (ItemInterface $item) use ($key) {
            $item->expiresAt(new \DateTime('+1 day'));

            return (string) $key->getPublicKey();
        });

        // Inject fake keys into apHttpClient
        $apHttpClient = new ApHttpClient(
            $domain,
            $this->tombstoneFactory,
            $this->personFactory,
            $this->groupFactory,
            $logger ?? $this->logger,
            $cache,
            $this->userRepository,
            $this->magazineRepository,
            $this->siteRepository,
            $this->projectInfoService,
        );
        self::getContainer()->set(ApHttpClientInterface::class, $apHttpClient);
    }
}
