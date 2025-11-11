<?php

declare(strict_types=1);

namespace App\Tests;

use App\Factory\ActivityPub\EntryPageFactory;
use App\Factory\ActivityPub\GroupFactory;
use App\Factory\ActivityPub\PersonFactory;
use App\Factory\ActivityPub\TombstoneFactory;
use App\Factory\ImageFactory;
use App\Factory\MagazineFactory;
use App\Markdown\MarkdownConverter;
use App\MessageHandler\ActivityPub\Outbox\DeliverHandler;
use App\Repository\ActivityRepository;
use App\Repository\BookmarkListRepository;
use App\Repository\BookmarkRepository;
use App\Repository\EntryCommentRepository;
use App\Repository\EntryRepository;
use App\Repository\ImageRepository;
use App\Repository\InstanceRepository;
use App\Repository\MagazineBanRepository;
use App\Repository\MagazineRepository;
use App\Repository\MagazineSubscriptionRepository;
use App\Repository\MessageRepository;
use App\Repository\NotificationRepository;
use App\Repository\PostCommentRepository;
use App\Repository\PostRepository;
use App\Repository\ReportRepository;
use App\Repository\SettingsRepository;
use App\Repository\SiteRepository;
use App\Repository\TagLinkRepository;
use App\Repository\UserFollowRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\ActivityJsonBuilder;
use App\Service\ActivityPub\ApHttpClientInterface;
use App\Service\ActivityPub\Wrapper\CreateWrapper;
use App\Service\ActivityPub\Wrapper\LikeWrapper;
use App\Service\ActivityPubManager;
use App\Service\BadgeManager;
use App\Service\BookmarkManager;
use App\Service\DomainManager;
use App\Service\EntryCommentManager;
use App\Service\EntryManager;
use App\Service\FavouriteManager;
use App\Service\ImageManager;
use App\Service\InstanceManager;
use App\Service\MagazineManager;
use App\Service\MentionManager;
use App\Service\MessageManager;
use App\Service\NotificationManager;
use App\Service\PostCommentManager;
use App\Service\PostManager;
use App\Service\ProjectInfoService;
use App\Service\ReportManager;
use App\Service\SettingsManager;
use App\Service\UserManager;
use App\Service\VoteManager;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class WebTestCase extends BaseWebTestCase
{
    use FactoryTrait;
    use OAuth2FlowTrait;
    use ValidationTrait;

    protected const PAGINATED_KEYS = ['items', 'pagination'];
    protected const PAGINATION_KEYS = ['count', 'currentPage', 'maxPage', 'perPage'];
    protected const IMAGE_KEYS = ['filePath', 'sourceUrl', 'storageUrl', 'altText', 'width', 'height', 'blurHash'];
    protected const MESSAGE_RESPONSE_KEYS = ['messageId', 'threadId', 'sender', 'body', 'status', 'createdAt'];
    protected const USER_RESPONSE_KEYS = ['userId', 'username', 'about', 'avatar', 'cover', 'createdAt', 'followersCount', 'apId', 'apProfileId', 'isBot', 'isFollowedByUser', 'isFollowerOfUser', 'isBlockedByUser', 'isAdmin', 'isGlobalModerator', 'serverSoftware', 'serverSoftwareVersion', 'notificationStatus', 'reputationPoints'];
    protected const USER_SMALL_RESPONSE_KEYS = ['userId', 'username', 'isBot', 'isFollowedByUser', 'isFollowerOfUser', 'isBlockedByUser', 'avatar', 'apId', 'apProfileId', 'createdAt', 'isAdmin', 'isGlobalModerator'];
    protected const ENTRY_RESPONSE_KEYS = ['entryId', 'magazine', 'user', 'domain', 'title', 'url', 'image', 'body', 'lang', 'tags', 'badges', 'numComments', 'uv', 'dv', 'favourites', 'isFavourited', 'userVote', 'isOc', 'isAdult', 'isPinned', 'createdAt', 'editedAt', 'lastActive', 'visibility', 'type', 'slug', 'apId', 'canAuthUserModerate', 'notificationStatus', 'bookmarks', 'crosspostedEntries', 'isAuthorModeratorInMagazine'];
    protected const ENTRY_COMMENT_RESPONSE_KEYS = ['commentId', 'magazine', 'user', 'entryId', 'parentId', 'rootId', 'image', 'body', 'lang', 'isAdult', 'uv', 'dv', 'favourites', 'isFavourited', 'userVote', 'visibility', 'apId', 'mentions', 'tags', 'createdAt', 'editedAt', 'lastActive', 'childCount', 'children', 'canAuthUserModerate', 'bookmarks', 'isAuthorModeratorInMagazine'];
    protected const POST_RESPONSE_KEYS = ['postId', 'user', 'magazine', 'image', 'body', 'lang', 'isAdult', 'isPinned', 'comments', 'uv', 'dv', 'favourites', 'isFavourited', 'userVote', 'visibility', 'apId', 'tags', 'mentions', 'createdAt', 'editedAt', 'lastActive', 'slug', 'canAuthUserModerate', 'notificationStatus', 'bookmarks', 'isAuthorModeratorInMagazine'];
    protected const POST_COMMENT_RESPONSE_KEYS = ['commentId', 'user', 'magazine', 'postId', 'parentId', 'rootId', 'image', 'body', 'lang', 'isAdult', 'uv', 'dv', 'favourites', 'isFavourited', 'userVote', 'visibility', 'apId', 'mentions', 'tags', 'createdAt', 'editedAt', 'lastActive', 'childCount', 'children', 'canAuthUserModerate', 'bookmarks', 'isAuthorModeratorInMagazine'];
    protected const BAN_RESPONSE_KEYS = ['banId', 'reason', 'expired', 'expiredAt', 'bannedUser', 'bannedBy', 'magazine'];
    protected const LOG_ENTRY_KEYS = ['type', 'createdAt', 'magazine', 'moderator', 'subject'];
    protected const MAGAZINE_RESPONSE_KEYS = ['magazineId', 'owner', 'icon', 'banner', 'name', 'title', 'description', 'rules', 'subscriptionsCount', 'entryCount', 'entryCommentCount', 'postCount', 'postCommentCount', 'isAdult', 'isUserSubscribed', 'isBlockedByUser', 'tags', 'badges', 'moderators', 'apId', 'apProfileId', 'serverSoftware', 'serverSoftwareVersion', 'isPostingRestrictedToMods', 'localSubscribers', 'notificationStatus'];
    protected const MAGAZINE_SMALL_RESPONSE_KEYS = ['magazineId', 'name', 'icon', 'banner', 'isUserSubscribed', 'isBlockedByUser', 'apId', 'apProfileId'];
    protected const DOMAIN_RESPONSE_KEYS = ['domainId', 'name', 'entryCount', 'subscriptionsCount', 'isUserSubscribed', 'isBlockedByUser'];

    protected const KIBBY_PNG_URL_RESULT = 'a8/1c/a81cc2fea35eeb232cd28fcb109b3eb5a4e52c71bce95af6650d71876c1bcbb7.png';

    protected ArrayCollection $users;
    protected ArrayCollection $magazines;
    protected ArrayCollection $entries;

    protected EntityManagerInterface $entityManager;
    protected KernelBrowser $client;

    protected MagazineManager $magazineManager;
    protected UserManager $userManager;
    protected EntryManager $entryManager;
    protected EntryCommentManager $entryCommentManager;
    protected PostManager $postManager;
    protected PostCommentManager $postCommentManager;
    protected ImageManager $imageManager;
    protected MessageManager $messageManager;
    protected FavouriteManager $favouriteManager;
    protected VoteManager $voteManager;
    protected SettingsManager $settingsManager;
    protected DomainManager $domainManager;
    protected ReportManager $reportManager;
    protected BadgeManager $badgeManager;
    protected NotificationManager $notificationManager;
    protected MentionManager $mentionManager;
    protected ActivityPubManager $activityPubManager;
    protected BookmarkManager $bookmarkManager;
    protected MarkdownConverter $markdownConverter;
    protected InstanceManager $instanceManager;

    protected MagazineRepository $magazineRepository;
    protected EntryRepository $entryRepository;
    protected EntryCommentRepository $entryCommentRepository;
    protected PostRepository $postRepository;
    protected PostCommentRepository $postCommentRepository;
    protected ImageRepository $imageRepository;
    protected MessageRepository $messageRepository;
    protected SiteRepository $siteRepository;
    protected NotificationRepository $notificationRepository;
    protected ReportRepository $reportRepository;
    protected SettingsRepository $settingsRepository;
    protected UserRepository $userRepository;
    protected TagLinkRepository $tagLinkRepository;
    protected BookmarkRepository $bookmarkRepository;
    protected BookmarkListRepository $bookmarkListRepository;
    protected UserFollowRepository $userFollowRepository;
    protected MagazineSubscriptionRepository $magazineSubscriptionRepository;
    protected ActivityRepository $activityRepository;
    protected InstanceRepository $instanceRepository;
    protected MagazineBanRepository $magazineBanRepository;

    protected ImageFactory $imageFactory;
    protected MagazineFactory $magazineFactory;
    protected TombstoneFactory $tombstoneFactory;
    protected PersonFactory $personFactory;
    protected GroupFactory $groupFactory;
    protected EntryPageFactory $pageFactory;
    protected TestingApHttpClient $testingApHttpClient;

    protected CreateWrapper $createWrapper;
    protected LikeWrapper $likeWrapper;

    protected UrlGeneratorInterface $urlGenerator;
    protected TranslatorInterface $translator;
    protected EventDispatcherInterface $eventDispatcher;
    protected RequestStack $requestStack;
    protected LoggerInterface $logger;
    protected ProjectInfoService $projectInfoService;
    protected RouterInterface $router;
    protected MessageBusInterface $bus;
    protected ActivityJsonBuilder $activityJsonBuilder;

    protected DeliverHandler $deliverHandler;

    protected string $kibbyPath;

    public function setUp(): void
    {
        $this->users = new ArrayCollection();
        $this->magazines = new ArrayCollection();
        $this->entries = new ArrayCollection();
        $this->kibbyPath = \dirname(__FILE__).'/assets/kibby_emoji.png';
        $this->client = static::createClient();

        $this->testingApHttpClient = new TestingApHttpClient();
        self::getContainer()->set(ApHttpClientInterface::class, $this->testingApHttpClient);

        $this->entityManager = $this->getService(EntityManagerInterface::class);
        $this->magazineManager = $this->getService(MagazineManager::class);
        $this->userManager = $this->getService(UserManager::class);
        $this->entryManager = $this->getService(EntryManager::class);
        $this->entryCommentManager = $this->getService(EntryCommentManager::class);
        $this->postManager = $this->getService(PostManager::class);
        $this->postCommentManager = $this->getService(PostCommentManager::class);
        $this->imageManager = $this->getService(ImageManager::class);
        $this->messageManager = $this->getService(MessageManager::class);
        $this->favouriteManager = $this->getService(FavouriteManager::class);
        $this->voteManager = $this->getService(VoteManager::class);
        $this->settingsManager = $this->getService(SettingsManager::class);
        $this->domainManager = $this->getService(DomainManager::class);
        $this->reportManager = $this->getService(ReportManager::class);
        $this->badgeManager = $this->getService(BadgeManager::class);
        $this->notificationManager = $this->getService(NotificationManager::class);
        $this->activityPubManager = $this->getService(ActivityPubManager::class);
        $this->bookmarkManager = $this->getService(BookmarkManager::class);
        $this->markdownConverter = $this->getService(MarkdownConverter::class);
        $this->instanceManager = $this->getService(InstanceManager::class);
        $this->activityJsonBuilder = $this->getService(ActivityJsonBuilder::class);
        $this->mentionManager = $this->getService(MentionManager::class);

        $this->magazineRepository = $this->getService(MagazineRepository::class);
        $this->entryRepository = $this->getService(EntryRepository::class);
        $this->entryCommentRepository = $this->getService(EntryCommentRepository::class);
        $this->postRepository = $this->getService(PostRepository::class);
        $this->postCommentRepository = $this->getService(PostCommentRepository::class);
        $this->imageRepository = $this->getService(ImageRepository::class);
        $this->messageRepository = $this->getService(MessageRepository::class);
        $this->siteRepository = $this->getService(SiteRepository::class);
        $this->notificationRepository = $this->getService(NotificationRepository::class);
        $this->reportRepository = $this->getService(ReportRepository::class);
        $this->settingsRepository = $this->getService(SettingsRepository::class);
        $this->userRepository = $this->getService(UserRepository::class);
        $this->tagLinkRepository = $this->getService(TagLinkRepository::class);
        $this->bookmarkRepository = $this->getService(BookmarkRepository::class);
        $this->bookmarkListRepository = $this->getService(BookmarkListRepository::class);
        $this->userFollowRepository = $this->getService(UserFollowRepository::class);
        $this->magazineSubscriptionRepository = $this->getService(MagazineSubscriptionRepository::class);
        $this->activityRepository = $this->getService(ActivityRepository::class);
        $this->instanceRepository = $this->getService(InstanceRepository::class);
        $this->magazineBanRepository = $this->getService(MagazineBanRepository::class);

        $this->imageFactory = $this->getService(ImageFactory::class);
        $this->personFactory = $this->getService(PersonFactory::class);
        $this->magazineFactory = $this->getService(MagazineFactory::class);
        $this->groupFactory = $this->getService(GroupFactory::class);
        $this->pageFactory = $this->getService(EntryPageFactory::class);

        $this->createWrapper = $this->getService(CreateWrapper::class);
        $this->likeWrapper = $this->getService(LikeWrapper::class);

        $this->urlGenerator = $this->getService(UrlGeneratorInterface::class);
        $this->translator = $this->getService(TranslatorInterface::class);
        $this->eventDispatcher = $this->getService(EventDispatcherInterface::class);
        $this->requestStack = $this->getService(RequestStack::class);
        $this->router = $this->getService(RouterInterface::class);
        $this->bus = $this->getService(MessageBusInterface::class);

        // clear all cache before every test
        $app = new Application($this->client->getKernel());
        $command = $app->get('cache:pool:clear');
        $tester = new CommandTester($command);
        $tester->execute(['--all' => '1']);
    }

    /**
     * @template T
     *
     * @param class-string<T> $className
     *
     * @return T
     */
    private function getService(string $className)
    {
        return $this->getContainer()->get($className);
    }

    public static function getJsonResponse(KernelBrowser $client): array
    {
        $response = $client->getResponse();
        self::assertJson($response->getContent());

        return json_decode($response->getContent(), associative: true);
    }

    /**
     * Checks that all values in array $keys are present as keys in array $value, and that no additional keys are included.
     */
    public static function assertArrayKeysMatch(array $keys, array $value, string $message = ''): void
    {
        $flipped = array_flip($keys);
        $difference = array_diff_key($value, $flipped);
        $diffString = json_encode(array_keys($difference));
        self::assertEmpty($difference, $message ? $message : "Extra keys were found in the provided array: $diffString");
        $intersect = array_intersect_key($value, $flipped);
        self::assertCount(\count($flipped), $intersect, $message);
    }

    public static function assertNotReached(string $message = 'This branch should never happen'): void
    {
        self::assertFalse(true, $message);
    }

    public static function removeTimeElements(string $content): string
    {
        $pattern = '/<time[ \w="\n-:]*>[\w \n]+<\/time>/m';

        return preg_replace($pattern, '', $content);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $entityManager = $this->entityManager;
        if ($entityManager->isOpen()) {
            $entityManager->close();
        }
    }
}
