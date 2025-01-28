<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub;

use App\ActivityPub\JsonRd;
use App\Entity\Magazine;
use App\Entity\User;
use App\Event\ActivityPub\WebfingerResponseEvent;
use App\Message\ActivityPub\Inbox\CreateMessage;
use App\Message\ActivityPub\Inbox\LikeMessage;
use App\Service\ActivityPub\Webfinger\WebFingerFactory;
use App\Tests\WebTestCase;
use Doctrine\Common\Collections\ArrayCollection;

class TagMatchTest extends WebTestCase
{
    private array $domains = [
        'mbin1.tld',
        'mbin2.tld',
        'mbin3.tld',
        'mbin4.tld',
        'mbin5.tld',
        'mbin6.tld',
        'mbin7.tld',
        'mbin8.tld',
        'mbin9.tld',
        'mbin10.tld',
    ];

    /** @var User[] */
    private array $remoteUsers = [];

    /** @var Magazine[] */
    private array $remoteMagazines = [];

    /**
     * Create 10 remote users ('user1'...'user10') and 10 remote magazines (all called mbin) with their webfingers
     * and 1 entry in each of the magazines.
     */
    public function createMockedRemoteObjects(): void
    {
        $prevDomain = $this->settingsManager->get('KBIN_DOMAIN');

        foreach ($this->domains as $domain) {
            $this->settingsManager->set('KBIN_DOMAIN', $domain);
            $context = $this->router->getContext();
            $context->setHost($domain);

            $username = 'user';
            $user = $this->getUserByUsername($username);
            $json = $this->personFactory->create($user);
            $this->testingApHttpClient->actorObjects[$json['id']] = $json;

            $userEvent = new WebfingerResponseEvent(new JsonRd(), "$username@$domain", ['account' => $username]);
            $this->eventDispatcher->dispatch($userEvent);
            $realDomain = \sprintf(WebFingerFactory::WEBFINGER_URL, 'https', $domain, '', "$username@$domain");
            $this->testingApHttpClient->webfingerObjects[$realDomain] = $userEvent->jsonRd->toArray();

            $magazineName = 'mbin';
            $magazine = $this->getMagazineByName($magazineName, user: $user);
            $json = $this->groupFactory->create($magazine);
            $this->testingApHttpClient->actorObjects[$json['id']] = $json;

            $magazineEvent = new WebfingerResponseEvent(new JsonRd(), "$magazineName@$domain", ['account' => $magazineName]);
            $this->eventDispatcher->dispatch($magazineEvent);
            $realDomain = \sprintf(WebFingerFactory::WEBFINGER_URL, 'https', $domain, '', "$magazineName@$domain");
            $this->testingApHttpClient->webfingerObjects[$realDomain] = $magazineEvent->jsonRd->toArray();

            $entry = $this->getEntryByTitle("test from $domain", magazine: $magazine, user: $user);
            $json = $this->pageFactory->create($entry, $this->tagLinkRepository->getTagsOfEntry($entry));
            $this->testingApHttpClient->activityObjects[$json['id']] = $json;

            $activity = $this->createWrapper->build($entry);
            $create = $this->activityJsonBuilder->buildActivityJson($activity);
            $this->testingApHttpClient->activityObjects[$create['id']] = $create;

            $this->entityManager->remove($activity);
            $this->entityManager->remove($entry);
            $this->entityManager->remove($magazine);
            $this->entityManager->remove($user);
            $this->entityManager->flush();
            $this->entityManager->clear();

            $this->entries = new ArrayCollection();
            $this->magazines = new ArrayCollection();
            $this->users = new ArrayCollection();
        }

        $this->settingsManager->set('KBIN_DOMAIN', $prevDomain);
        $context = $this->router->getContext();
        $context->setHost($prevDomain);

        $this->testingApHttpClient->actorObjects[$this->mastodonUser['id']] = $this->mastodonUser;
        $this->testingApHttpClient->activityObjects[$this->mastodonPost['id']] = $this->mastodonPost;
        $this->testingApHttpClient->webfingerObjects[\sprintf(WebFingerFactory::WEBFINGER_URL, 'https', 'masto.don', '', 'User@masto.don')] = $this->mastodonWebfinger;
    }

    public function setUp(): void
    {
        sort($this->domains);
        parent::setUp();

        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $this->getMagazineByName('random', user: $admin);

        $this->createMockedRemoteObjects();
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByName('matching_mbin', user: $user);
        $magazine->title = 'Matching Mbin';
        $magazine->tags = ['mbin'];
        $this->entityManager->persist($magazine);
        $this->entityManager->flush();

        foreach ($this->domains as $domain) {
            $this->remoteUsers[] = $this->activityPubManager->findActorOrCreate("user@$domain");
            $this->remoteMagazines[] = $this->activityPubManager->findActorOrCreate("mbin@$domain");
        }

        foreach ($this->remoteUsers as $remoteUser) {
            $this->magazineManager->subscribe($magazine, $remoteUser);
        }

        foreach ($this->remoteMagazines as $remoteMagazine) {
            $this->magazineManager->subscribe($remoteMagazine, $user);
        }
    }

    public function testMatching(): void
    {
        self::assertEquals(\sizeof($this->domains), \sizeof(array_filter($this->remoteMagazines)));
        self::assertEquals(\sizeof($this->domains), \sizeof(array_filter($this->remoteUsers)));

        $this->pullInRemoteEntries();
        $this->pullInMastodonPost();

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        $postedAnnounces = array_filter($postedObjects, fn ($item) => 'Announce' === $item['payload']['type']);
        $targetInboxes = array_map(fn ($item) => parse_url($item['inboxUrl'], PHP_URL_HOST), $postedAnnounces);
        sort($targetInboxes);
        self::assertArrayIsEqualToArrayIgnoringListOfKeys($this->domains, $targetInboxes, []);
    }

    public function testMatchingLikeAnnouncing(): void
    {
        self::assertEquals(\sizeof($this->domains), \sizeof(array_filter($this->remoteMagazines)));
        self::assertEquals(\sizeof($this->domains), \sizeof(array_filter($this->remoteUsers)));

        $this->pullInRemoteEntries();
        $this->pullInMastodonPost();

        $mastodonPost = $this->postRepository->findOneBy(['apId' => $this->mastodonPost['id']]);
        $user = $this->getUserByUsername('user');
        $this->favouriteManager->toggle($user, $mastodonPost);

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        $postedLikes = array_filter($postedObjects, fn ($item) => 'Like' === $item['payload']['type']);
        $targetInboxes2 = array_map(fn ($item) => parse_url($item['inboxUrl'], PHP_URL_HOST), $postedLikes);
        sort($targetInboxes2);

        // the pure like activity is expected to be sent to the author of the post
        $expectedInboxes = [...$this->domains, parse_url($mastodonPost->user->apInboxUrl, PHP_URL_HOST)];
        sort($expectedInboxes);
        self::assertArrayIsEqualToArrayIgnoringListOfKeys($expectedInboxes, $targetInboxes2, []);

        // dispatch a remote like message, so we trigger the announcement of it
        $activity = $this->likeWrapper->build($this->remoteUsers[0], $mastodonPost);
        $json = $this->activityJsonBuilder->buildActivityJson($activity);
        $this->testingApHttpClient->activityObjects[$json['id']] = $json;
        $this->bus->dispatch(new LikeMessage($json));

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        $postedLikeAnnounces = array_filter($postedObjects, fn ($item) => 'Announce' === $item['payload']['type'] && 'Like' === $item['payload']['object']['type']);
        $targetInboxes3 = array_map(fn ($item) => parse_url($item['inboxUrl'], PHP_URL_HOST), $postedLikeAnnounces);
        sort($targetInboxes3);

        // the announcement of the like is expected to be delivered only to the subscribers of the magazine,
        // because we expect the pure like activity to already be sent to the author of the post by the remote server
        self::assertArrayIsEqualToArrayIgnoringListOfKeys($this->domains, $targetInboxes3, []);
    }

    private function pullInRemoteEntries(): void
    {
        foreach (array_filter($this->testingApHttpClient->activityObjects, fn ($item) => 'Page' === $item['type']) as $apObject) {
            $this->bus->dispatch(new CreateMessage($apObject));
            $entry = $this->entryRepository->findOneBy(['apId' => $apObject['id']]);
            self::assertNotNull($entry);
        }
    }

    private function pullInMastodonPost(): void
    {
        $this->bus->dispatch(new CreateMessage($this->mastodonPost));
    }

    private array $mastodonUser = [
        'id' => 'https://masto.don/users/User',
        'type' => 'Person',
        'following' => 'https://masto.don/users/User/following',
        'followers' => 'https://masto.don/users/User/followers',
        'inbox' => 'https://masto.don/users/User/inbox',
        'outbox' => 'https://masto.don/users/User/outbox',
        'featured' => 'https://masto.don/users/User/collections/featured',
        'featuredTags' => 'https://masto.don/users/User/collections/tags',
        'preferredUsername' => 'User',
        'name' => 'User',
        'summary' => '<p>Some summary</p>',
        'url' => 'https://masto.don/@User',
        'manuallyApprovesFollowers' => false,
        'discoverable' => true,
        'indexable' => true,
        'published' => '2025-01-01T00:00:00Z',
        'memorial' => false,
        'publicKey' => [
            'id' => 'https://masto.don/users/User#main-key',
            'owner' => 'https://masto.don/users/User',
            'publicKeyPem' => "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAujdiYalTtr7R1CJIVBIy\nP50V+/JX+P15o0Cz0LUOhKvJIVyeV6szQGHj6Idu74x9e3+xf9jzQRCH6eq8ASAH\nHAKwdnHfhSmKbCQaTEI5V8497/4yU9z9Zn7uJ+C1rrKVIEoGGkpt8bK8fynfR/hb\n17FctW6EnrVrvNHyW+WwbyEbyqAxwbcOYd78PhdftWEdP6D+t4+XUoF9N1XGpsGO\nrixJDzMwNqkg9Gg9l/mnCmxV367xgh8qHC0SNmwaMbWv6AV/07dHWlr0N1pXmHqo\n9YkOEy7XuH1hovBzHWEf++P1Ew4bstwdfyS/m5bcakmSe+dR3WDylW336nO88vAF\nCQIDAQAB\n-----END PUBLIC KEY-----\n",
        ],
        'tag' => [],
        'attachment' => [],
        'endpoints' => [
            'sharedInbox' => 'https://masto.don/inbox',
        ],
    ];

    private array $mastodonPost = [
        'id' => 'https://masto.don/users/User/statuses/110226274955756643',
        'type' => 'Note',
        'summary' => null,
        'inReplyTo' => null,
        'published' => '2025-01-01T15:51:18Z',
        'url' => 'https://masto.don/@User/110226274955756643',
        'attributedTo' => 'https://masto.don/users/User',
        'to' => [
            'https://www.w3.org/ns/activitystreams#Public',
        ],
        'cc' => [
            'https://masto.don/users/User/followers',
        ],
        'sensitive' => false,
        'atomUri' => 'https://masto.don/users/User/statuses/110226274955756643',
        'inReplyToAtomUri' => null,
        'conversation' => 'tag:masto.don,2025-01-01:objectId=399588:objectType=Conversation',
        'content' => '<p>I am very excited about <a href="https://masto.don/tags/mbin" class="mention hashtag" rel="tag">#<span>mbin</span></a></p>',
        'contentMap' => [
            'de' => '<p>I am very excited about <a href="https://masto.don/tags/mbin" class="mention hashtag" rel="tag">#<span>mbin</span></a></p>',
        ],
        'attachment' => [],
        'tag' => [
            [
                'type' => 'Hashtag',
                'href' => 'https://masto.don/tags/mbin',
                'name' => '#mbin',
            ],
        ],
        'replies' => [
            'id' => 'https://masto.don/users/User/statuses/110226274955756643/replies',
            'type' => 'Collection',
            'first' => [
                'type' => 'CollectionPage',
                'next' => 'https://masto.don/users/User/statuses/110226274955756643/replies?min_id=110226283102047096&page=true',
                'partOf' => 'https://masto.don/users/User/statuses/110226274955756643/replies',
                'items' => [
                    'https://masto.don/users/User/statuses/110226283102047096',
                ],
            ],
        ],
        'likes' => [
            'id' => 'https://masto.don/users/User/statuses/110226274955756643/likes',
            'type' => 'Collection',
            'totalItems' => 0,
        ],
        'shares' => [
            'id' => 'https://masto.don/users/User/statuses/110226274955756643/shares',
            'type' => 'Collection',
            'totalItems' => 0,
        ],
    ];

    private array $mastodonWebfinger = [
        'subject' => 'acct:User@masto.don',
        'aliases' => [
            'https://masto.don/@User',
            'https://masto.don/users/User',
        ],
        'links' => [
            [
                'rel' => 'http://webfinger.net/rel/profile-page',
                'type' => 'text/html',
                'href' => 'https://masto.don/@User',
            ],
            [
                'rel' => 'self',
                'type' => 'application/activity+json',
                'href' => 'https://masto.don/users/User',
            ],
            [
                'rel' => 'http://ostatus.org/schema/1.0/subscribe',
                'template' => 'https://masto.don/authorize_interaction?uri=[uri]',
            ],
        ],
    ];
}
