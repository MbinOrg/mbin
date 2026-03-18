<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Search;

use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPub\ApHttpClientInterface;
use App\Service\SettingsManager;
use App\Tests\Service\ApHttpClientProxy;
use App\Tests\WebTestCase;
use phpseclib3\Crypt\RSA;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class SearchApiTest extends WebTestCase
{

    // These tests do work, but we should not do requests to a remote server when running tests
    private const RUN_AP_SEARCHES = true;

    public const SEARCH_PAGINATED_KEYS = ['items', 'pagination', 'apResults'];
    public const SEARCH_ITEM_KEYS = ['entry', 'entryComment', 'post', 'postComment', 'magazine', 'user'];

    private RSA\PrivateKey $key;

    public function setUp(): void
    {
        parent::setUp();
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

        self::validateResponseOuterData($jsonData, 1, 0);
        self::validateResponseItemData($jsonData['items'][0], 'entry', $entry->getId());
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

        self::validateResponseOuterData($jsonData, 2, 0);

        foreach ($jsonData['items'] as $item) {
            if($item['entry'] !== null) {
                $type = 'entry';
                $id = $entry->getId();
            } else {
                $type = 'post';
                $id = $post->getId();
            }

            self::validateResponseItemData($item, $type, $id);
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

        self::validateResponseOuterData($jsonData, 2, 0);

        foreach ($jsonData['items'] as $item) {
            if($item['entryComment'] !== null) {
                $type = 'entryComment';
                $id = $entryComment->getId();
            } else {
                $type = 'postComment';
                $id = $postComment->getId();
            }

            self::validateResponseItemData($item, $type, $id);
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

        self::validateResponseOuterData($jsonData, 0, 0);

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

        self::validateResponseOuterData($jsonData, 0, 0);

        // Seems like settings can persist in the test environment? Might only be for bare metal setups
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', $value);
    }

    public function testApiCanFindRemoteUserByHandleAnonymous(): void
    {
        if(!self::RUN_AP_SEARCHES) return;

        $settingsManager = $this->settingsManager;
        $value = $settingsManager->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN');
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', false);
        $domain = $settingsManager->get('KBIN_DOMAIN');
        $this->getUserByUsername('test');
        $this->setCacheKeysForApHttpClient($domain);

        $this->client->request('GET', "/api/search?q=@eugen@mastodon.social");

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::validateResponseOuterData($jsonData, 0, 1);
        self::validateResponseItemData($jsonData['apResults'][0], 'user', null, 'eugen@mastodon.social');

        // Seems like settings can persist in the test environment? Might only be for bare metal setups
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', $value);
        self::getContainer()->get(ApHttpClientInterface::class)->replacement = null;
    }

    public function testApiCanFindRemoteMagazineByHandleAnonymous(): void
    {
        if(!self::RUN_AP_SEARCHES) return;

        // Admin user must exist to retrieve a remote magazine since remote mods aren't federated (yet)
        $this->getUserByUsername('admin', isAdmin: true);

        $settingsManager = $this->settingsManager;
        $value = $settingsManager->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN');
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', false);
        $domain = $settingsManager->get('KBIN_DOMAIN');
        $this->setCacheKeysForApHttpClient($domain, $this->logger);
        $this->getMagazineByName('testMag');

        $this->client->request('GET', "/api/search?q=!technology@lemmy.world");

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::validateResponseOuterData($jsonData, 0, 1);
        self::validateResponseItemData($jsonData['apResults'][0], 'magazine', null, 'technology@lemmy.world');

        // Seems like settings can persist in the test environment? Might only be for bare metal setups
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', $value);
        self::getContainer()->get(ApHttpClientInterface::class)->replacement = null;
    }

    public function testApiCanFindRemoteUserByUrl(): void
    {
        if(!self::RUN_AP_SEARCHES) return;

        $settingsManager = $this->settingsManager;
        $value = $settingsManager->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN');
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', true);
        $domain = $settingsManager->get('KBIN_DOMAIN');
        $this->setCacheKeysForApHttpClient($domain);
        $this->getUserByUsername('test');

        $this->client->loginUser($this->getUserByUsername('user'));

        $this->client->request('GET', "/api/search?q=https%3A%2F%2Fmastodon.social%2F%40eugen");

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::validateResponseOuterData($jsonData, 0, 1);
        self::validateResponseItemData($jsonData['apResults'][0], 'user', null, 'eugen@mastodon.social');

        // Seems like settings can persist in the test environment? Might only be for bare metal setups
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', $value);
        self::getContainer()->get(ApHttpClientInterface::class)->replacement = null;
    }

    public function testApiCanFindRemoteMagazineByUrl(): void
    {
        if(!self::RUN_AP_SEARCHES) return;

        $this->getUserByUsername('admin', isAdmin: true);

        $settingsManager = $this->settingsManager;
        $value = $settingsManager->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN');
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', true);
        $domain = $settingsManager->get('KBIN_DOMAIN');
        $this->setCacheKeysForApHttpClient($domain);

        $this->client->loginUser($this->getUserByUsername('user'));

        $this->getMagazineByName('testMag');

        $this->client->request('GET', "/api/search?q=https%3A%2F%2Flemmy.world%2Fc%2Ftechnology");

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::validateResponseOuterData($jsonData, 0, 1);
        self::validateResponseItemData($jsonData['apResults'][0], 'magazine', null, 'technology@lemmy.world');

        // Seems like settings can persist in the test environment? Might only be for bare metal setups
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', $value);
        self::getContainer()->get(ApHttpClientInterface::class)->replacement = null;
    }

    public function testApiCanFindRemotePostByUrl(): void
    {
        if(!self::RUN_AP_SEARCHES) return;

        $this->getUserByUsername('admin', isAdmin: true);

        $settingsManager = $this->settingsManager;
        $value = $settingsManager->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN');
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', true);
        $domain = $settingsManager->get('KBIN_DOMAIN');
        $this->setCacheKeysForApHttpClient($domain);

        $this->client->loginUser($this->getUserByUsername('user'));

        $this->getMagazineByName('testMag');

        $this->client->request('GET', "/api/search?q=https%3A%2F%2Flemmy.world%2Fpost%2F44358216");

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::validateResponseOuterData($jsonData, 0, 1);
        self::validateResponseItemData($jsonData['apResults'][0], 'entry', null, 'https://sh.itjust.works/post/56929452');

        // Seems like settings can persist in the test environment? Might only be for bare metal setups
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', $value);
        self::getContainer()->get(ApHttpClientInterface::class)->replacement = null;
    }

    private static function validateResponseOuterData(array $data, int $expectedLength, int $expectedApLength): void {
        self::assertIsArray($data);
        self::assertArrayKeysMatch(self::SEARCH_PAGINATED_KEYS, $data);
        self::assertIsArray($data['items']);
        self::assertCount($expectedLength, $data['items']);
        self::assertIsArray($data['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $data['pagination']);
        self::assertSame($expectedLength, $data['pagination']['count']);
        self::assertIsArray($data['apResults']);
        self::assertCount($expectedApLength, $data['apResults']);
    }

    private static function validateResponseItemData(array $data, string $expectedType, ?int $expectedId = null, ?string $expectedApId = null): void {
        self::assertIsArray($data);
        self::assertArrayKeysMatch(self::SEARCH_ITEM_KEYS, $data);

        switch ($expectedType) {
            case 'entry':
                self::assertNotNull($data['entry']);
                self::assertNull($data['entryComment']);
                self::assertNull($data['post']);
                self::assertNull($data['postComment']);
                self::assertNull($data['magazine']);
                self::assertNull($data['user']);
                self::assertArrayKeysMatch(self::ENTRY_RESPONSE_KEYS, $data['entry']);
                if($expectedId !== null) {
                    self::assertSame($expectedId, $data['entry']['entryId']);
                } else {
                    self::assertSame($expectedApId, $data['entry']['apId']);
                }
                break;
            case 'entryComment':
                self::assertNotNull($data['entryComment']);
                self::assertNull($data['entry']);
                self::assertNull($data['post']);
                self::assertNull($data['postComment']);
                self::assertNull($data['magazine']);
                self::assertNull($data['user']);
                self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $data['entryComment']);
                if($expectedId !== null) {
                    self::assertSame($expectedId, $data['entryComment']['commentId']);
                } else {
                    self::assertSame($expectedApId, $data['entryComment']['apId']);
                }
                break;
            case 'post':
                self::assertNotNull($data['post']);
                self::assertNull($data['entry']);
                self::assertNull($data['entryComment']);
                self::assertNull($data['postComment']);
                self::assertNull($data['magazine']);
                self::assertNull($data['user']);
                self::assertArrayKeysMatch(self::POST_RESPONSE_KEYS, $data['post']);
                if($expectedId !== null) {
                    self::assertSame($expectedId, $data['post']['postId']);
                } else {
                    self::assertSame($expectedApId, $data['post']['apId']);
                }
                break;
            case 'postComment':
                self::assertNotNull($data['postComment']);
                self::assertNull($data['entry']);
                self::assertNull($data['entryComment']);
                self::assertNull($data['post']);
                self::assertNull($data['magazine']);
                self::assertNull($data['user']);
                self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $data['postComment']);
                if($expectedId !== null) {
                    self::assertSame($expectedId, $data['postComment']['commentId']);
                } else {
                    self::assertSame($expectedApId, $data['postComment']['apId']);
                }
                break;
            case 'magazine':
                self::assertNotNull($data['magazine']);
                self::assertNull($data['entry']);
                self::assertNull($data['entryComment']);
                self::assertNull($data['post']);
                self::assertNull($data['postComment']);
                self::assertNull($data['user']);
                self::assertArrayKeysMatch(self::MAGAZINE_RESPONSE_KEYS, $data['magazine']);
                if($expectedId !== null) {
                    self::assertSame($expectedId, $data['magazine']['magazineId']);
                } else {
                    self::assertSame($expectedApId, $data['magazine']['apId']);
                }
                break;
            case 'user':
                self::assertNotNull($data['user']);
                self::assertNull($data['entry']);
                self::assertNull($data['entryComment']);
                self::assertNull($data['post']);
                self::assertNull($data['postComment']);
                self::assertNull($data['magazine']);
                self::assertArrayKeysMatch(self::USER_RESPONSE_KEYS, $data['user']);
                if($expectedId !== null) {
                    self::assertSame($expectedId, $data['user']['userId']);
                } else {
                    self::assertSame($expectedApId, $data['user']['apId']);
                }
                break;
            default:
                throw new \AssertionError();
        }
    }

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
            $this->eventDispatcher,
        );
        self::getContainer()->get(ApHttpClientInterface::class)->replacement = $apHttpClient;
    }
}
