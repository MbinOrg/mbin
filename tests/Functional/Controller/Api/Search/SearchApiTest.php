<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Search;

use App\Entity\Entry;
use App\Entity\Magazine;
use App\Entity\User;
use App\Tests\Functional\ActivityPub\ActivityPubFunctionalTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group(name: 'NonThreadSafe')]
class SearchApiTest extends ActivityPubFunctionalTestCase
{
    public const SEARCH_PAGINATED_KEYS = ['items', 'pagination', 'apResults'];
    public const SEARCH_ITEM_KEYS = ['entry', 'entryComment', 'post', 'postComment', 'magazine', 'user'];

    private const string TEST_USER_NAME = 'someremoteuser';
    private const string TEST_USER_HANDLE = self::TEST_USER_NAME.'@remote.mbin';
    private const string TEST_USER_URL = 'https://remote.mbin/u/'.self::TEST_USER_NAME;
    private const string TEST_MAGAZINE_NAME = 'someremotemagazine';
    private const string TEST_MAGAZINE_HANDLE = self::TEST_MAGAZINE_NAME.'@remote.mbin';
    private const string TEST_MAGAZINE_URL = 'https://remote.mbin/m/'.self::TEST_MAGAZINE_NAME;

    private string $testEntryUrl;

    private User $someUser;
    private Magazine $someMagazine;

    public function setUp(): void
    {
        parent::setUp();

        $this->someUser = $this->getUserByUsername('JohnDoe2', email: 'jd@test.tld');
        $this->someMagazine = $this->getMagazineByName('acme2', $this->someUser);
    }

    public function setUpRemoteEntities(): void
    {
        $this->createRemoteEntryInRemoteMagazine($this->remoteMagazine, $this->remoteUser, function (Entry $entry) {
            $this->testEntryUrl = 'https://remote.mbin/m/someremotemagazine/t/'.$entry->getId();
        });
    }

    protected function setUpRemoteActors(): void
    {
        parent::setUpRemoteActors();

        $this->remoteUser = $this->getUserByUsername(self::TEST_USER_NAME, addImage: false);
        $this->registerActor($this->remoteUser, $this->remoteDomain, true);

        $this->remoteMagazine = $this->getMagazineByName(self::TEST_MAGAZINE_NAME);
        $this->registerActor($this->remoteMagazine, $this->remoteDomain, true);
    }

    public function testApiCannotSearchWithNoQuery(): void
    {
        $this->client->request('GET', '/api/search/v2');

        self::assertResponseStatusCodeSame(400);
    }

    public function testApiCanFindEntryByTitleAnonymous(): void
    {
        $entry = $this->getEntryByTitle('A test title to search for', magazine: $this->someMagazine, user: $this->someUser);
        $this->getEntryByTitle('Cannot find this', magazine: $this->someMagazine, user: $this->someUser);

        $this->client->request('GET', '/api/search/v2?q=title');

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::validateResponseOuterData($jsonData, 1, 0);
        self::validateResponseItemData($jsonData['items'][0], 'entry', $entry->getId());
    }

    public function testApiCanFindContentByBodyAnonymous(): void
    {
        $entry = $this->getEntryByTitle('A test title to search for', body: 'This is the body we\'re finding', magazine: $this->someMagazine, user: $this->someUser);
        $this->getEntryByTitle('Cannot find this', body: 'No keywords here!', magazine: $this->someMagazine, user: $this->someUser);
        $post = $this->createPost('Lets get a post with its body in there too!', magazine: $this->someMagazine, user: $this->someUser);
        $this->createPost('But not this one.', magazine: $this->someMagazine, user: $this->someUser);

        $this->client->request('GET', '/api/search/v2?q=body');

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::validateResponseOuterData($jsonData, 2, 0);

        foreach ($jsonData['items'] as $item) {
            if (null !== $item['entry']) {
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
        $entry = $this->getEntryByTitle('Cannot find this', body: 'No keywords here!', magazine: $this->someMagazine, user: $this->someUser);
        $post = $this->createPost('But not this one.', magazine: $this->someMagazine, user: $this->someUser);
        $entryComment = $this->createEntryComment('Some comment on a thread', $entry, user: $this->someUser);
        $postComment = $this->createPostComment('Some comment on a post', $post, user: $this->someUser);

        $this->client->request('GET', '/api/search/v2?q=comment');

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::validateResponseOuterData($jsonData, 2, 0);

        foreach ($jsonData['items'] as $item) {
            if (null !== $item['entryComment']) {
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

        $this->client->request('GET', '/api/search/v2?q='.self::TEST_USER_HANDLE);

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

        $this->client->request('GET', '/api/search/v2?q='.self::TEST_MAGAZINE_HANDLE);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::validateResponseOuterData($jsonData, 0, 0);

        // Seems like settings can persist in the test environment? Might only be for bare metal setups
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', $value);
    }

    public function testApiCanFindRemoteUserByHandleAnonymous(): void
    {
        $settingsManager = $this->settingsManager;
        $value = $settingsManager->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN');
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', false);
        $this->getUserByUsername('test');

        $this->client->request('GET', '/api/search/v2?q=@'.self::TEST_USER_HANDLE);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::validateResponseOuterData($jsonData, 0, 1);
        self::validateResponseItemData($jsonData['apResults'][0], 'user', null, self::TEST_USER_HANDLE, self::TEST_USER_URL);

        $this->client->request('GET', '/api/search/v2?q='.self::TEST_USER_HANDLE);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::validateResponseOuterData($jsonData, 1, 1);
        self::assertSame(self::TEST_USER_URL, $jsonData['items'][0]['user']['apProfileId']);
        self::validateResponseItemData($jsonData['apResults'][0], 'user', null, self::TEST_USER_HANDLE, self::TEST_USER_URL);

        // Seems like settings can persist in the test environment? Might only be for bare metal setups.
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', $value);
    }

    public function testApiCanFindRemoteMagazineByHandleAnonymous(): void
    {
        // Admin user must exist to retrieve a remote magazine since remote mods aren't federated (yet)
        $this->getUserByUsername('admin', isAdmin: true);

        $settingsManager = $this->settingsManager;
        $value = $settingsManager->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN');
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', false);
        $this->getMagazineByName('testMag', user: $this->someUser);

        $this->client->request('GET', '/api/search/v2?q=!'.self::TEST_MAGAZINE_HANDLE);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::validateResponseOuterData($jsonData, 0, 1);
        self::validateResponseItemData($jsonData['apResults'][0], 'magazine', null, self::TEST_MAGAZINE_HANDLE, self::TEST_MAGAZINE_URL);

        // Seems like settings can persist in the test environment? Might only be for bare metal setups
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', $value);
    }

    public function testApiCanFindRemoteUserByUrl(): void
    {
        $settingsManager = $this->settingsManager;
        $value = $settingsManager->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN');
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', true);
        $this->getUserByUsername('test');

        $this->client->loginUser($this->localUser);

        $this->client->request('GET', '/api/search/v2?q='.urlencode(self::TEST_USER_URL));

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::validateResponseOuterData($jsonData, 0, 1);
        self::validateResponseItemData($jsonData['apResults'][0], 'user', null, self::TEST_USER_HANDLE, self::TEST_USER_URL);

        // Seems like settings can persist in the test environment? Might only be for bare metal setups
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', $value);
    }

    public function testApiCanFindRemoteMagazineByUrl(): void
    {
        $this->getUserByUsername('admin', isAdmin: true);

        $settingsManager = $this->settingsManager;
        $value = $settingsManager->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN');
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', true);

        $this->client->loginUser($this->localUser);

        $this->getMagazineByName('testMag', user: $this->someUser);

        $this->client->request('GET', '/api/search/v2?q='.urlencode(self::TEST_MAGAZINE_URL));

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::validateResponseOuterData($jsonData, 0, 1);
        self::validateResponseItemData($jsonData['apResults'][0], 'magazine', null, self::TEST_MAGAZINE_HANDLE, self::TEST_MAGAZINE_URL);

        // Seems like settings can persist in the test environment? Might only be for bare metal setups
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', $value);
    }

    public function testApiCanFindRemotePostByUrl(): void
    {
        $this->getUserByUsername('admin', isAdmin: true);

        $settingsManager = $this->settingsManager;
        $value = $settingsManager->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN');
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', true);

        $this->client->loginUser($this->localUser);

        $this->getMagazineByName('testMag', user: $this->someUser);

        $this->client->request('GET', '/api/search/v2?q='.urlencode($this->testEntryUrl));

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::validateResponseOuterData($jsonData, 0, 1);
        self::validateResponseItemData($jsonData['apResults'][0], 'entry', null, $this->testEntryUrl);

        // Seems like settings can persist in the test environment? Might only be for bare metal setups
        $settingsManager->set('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', $value);
    }

    private static function validateResponseOuterData(array $data, int $expectedLength, int $expectedApLength): void
    {
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

    private static function validateResponseItemData(array $data, string $expectedType, ?int $expectedId = null, ?string $expectedApId = null, ?string $apProfileId = null): void
    {
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
                if (null !== $expectedId) {
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
                if (null !== $expectedId) {
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
                if (null !== $expectedId) {
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
                if (null !== $expectedId) {
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
                if (null !== $expectedId) {
                    self::assertSame($expectedId, $data['magazine']['magazineId']);
                } else {
                    self::assertSame($expectedApId, $data['magazine']['apId']);
                }
                if (null !== $apProfileId) {
                    self::assertSame($apProfileId, $data['magazine']['apProfileId']);
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
                if (null !== $expectedId) {
                    self::assertSame($expectedId, $data['user']['userId']);
                } else {
                    self::assertSame($expectedApId, $data['user']['apId']);
                }
                if (null !== $apProfileId) {
                    self::assertSame($apProfileId, $data['user']['apProfileId']);
                }
                break;
            default:
                throw new \AssertionError();
        }
    }
}
