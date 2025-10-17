<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\DTO\EntryCommentDto;
use App\DTO\EntryCommentResponseDto;
use App\DTO\EntryDto;
use App\DTO\EntryResponseDto;
use App\DTO\MagazineDto;
use App\DTO\MagazineResponseDto;
use App\DTO\PostCommentDto;
use App\DTO\PostCommentResponseDto;
use App\DTO\PostDto;
use App\DTO\PostResponseDto;
use App\DTO\ReportDto;
use App\DTO\ReportRequestDto;
use App\DTO\UserDto;
use App\DTO\UserResponseDto;
use App\Entity\Client;
use App\Entity\Contracts\ContentInterface;
use App\Entity\Contracts\ContentVisibilityInterface;
use App\Entity\Contracts\ReportInterface;
use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Image;
use App\Entity\MagazineLog;
use App\Entity\OAuth2ClientAccess;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Enums\ENotificationStatus;
use App\Exception\SubjectHasBeenReportedException;
use App\Factory\EntryCommentFactory;
use App\Factory\EntryFactory;
use App\Factory\ImageFactory;
use App\Factory\MagazineFactory;
use App\Factory\PostCommentFactory;
use App\Factory\PostFactory;
use App\Factory\UserFactory;
use App\Form\Constraint\ImageConstraint;
use App\Repository\BookmarkListRepository;
use App\Repository\BookmarkRepository;
use App\Repository\Criteria;
use App\Repository\EntryCommentRepository;
use App\Repository\EntryRepository;
use App\Repository\ImageRepository;
use App\Repository\NotificationSettingsRepository;
use App\Repository\OAuth2ClientAccessRepository;
use App\Repository\PostCommentRepository;
use App\Repository\PostRepository;
use App\Repository\ReputationRepository;
use App\Repository\TagLinkRepository;
use App\Repository\UserRepository;
use App\Schema\PaginationSchema;
use App\Service\BookmarkManager;
use App\Service\IpResolver;
use App\Service\ReportManager;
use App\Service\SettingsManager;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\AccessToken;
use League\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Pagerfanta\PagerfantaInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints\Image as BaseImageConstraint;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BaseApi extends AbstractController
{
    public const MIN_PER_PAGE = 1;
    public const MAX_PER_PAGE = 100;
    public const DEPTH = 10;
    public const MIN_DEPTH = 0;
    public const MAX_DEPTH = 25;

    /** @var BaseImageConstraint */
    private static $constraint;

    public function __construct(
        protected readonly IpResolver $ipResolver,
        protected readonly LoggerInterface $logger,
        protected readonly SerializerInterface $serializer,
        protected readonly ValidatorInterface $validator,
        protected readonly EntityManagerInterface $entityManager,
        protected readonly ImageFactory $imageFactory,
        protected readonly PostFactory $postFactory,
        protected readonly PostCommentFactory $postCommentFactory,
        protected readonly EntryFactory $entryFactory,
        protected readonly EntryCommentFactory $entryCommentFactory,
        protected readonly MagazineFactory $magazineFactory,
        protected readonly RequestStack $request,
        protected readonly TagLinkRepository $tagLinkRepository,
        protected readonly EntryRepository $entryRepository,
        protected readonly EntryCommentRepository $entryCommentRepository,
        protected readonly PostRepository $postRepository,
        protected readonly PostCommentRepository $postCommentRepository,
        protected readonly BookmarkListRepository $bookmarkListRepository,
        protected readonly BookmarkRepository $bookmarkRepository,
        protected readonly BookmarkManager $bookmarkManager,
        protected readonly UserManager $userManager,
        protected readonly UserRepository $userRepository,
        private readonly ImageRepository $imageRepository,
        private readonly ReportManager $reportManager,
        private readonly OAuth2ClientAccessRepository $clientAccessRepository,
        protected readonly NotificationSettingsRepository $notificationSettingsRepository,
        protected readonly SettingsManager $settingsManager,
        protected readonly UserFactory $userFactory,
        protected readonly ReputationRepository $reputationRepository,
    ) {
    }

    /**
     * Rate limit an API request and return rate limit status headers.
     *
     * @param ?RateLimiterFactory $limiterFactory     A limiter factory to use when the user is authenticated
     * @param ?RateLimiterFactory $anonLimiterFactory A limiter factory to use when the user is anonymous
     *
     * @return array<string, int> An array of headers describing the current rate limit status to the client
     *
     * @throws AccessDeniedHttpException    if the user is not authenticated and no anonymous rate limiter factory is provided, access to the resource will be denied
     * @throws TooManyRequestsHttpException If the limit is hit, rate limit the connection
     */
    protected function rateLimit(
        ?RateLimiterFactory $limiterFactory = null,
        ?RateLimiterFactory $anonLimiterFactory = null,
    ): array {
        $this->logAccess();
        if (null === $limiterFactory && null === $anonLimiterFactory) {
            throw new \LogicException('No rate limiter factory provided!');
        }
        $limiter = null;
        if (
            $limiterFactory && $this->isGranted('ROLE_USER')
        ) {
            $limiter = $limiterFactory->create($this->getUserOrThrow()->getUserIdentifier());
        } elseif ($anonLimiterFactory) {
            $limiter = $anonLimiterFactory->create($this->ipResolver->resolve());
        } else {
            // non-API_USER without an anonymous rate limiter? Not allowed.
            throw new AccessDeniedHttpException();
        }
        $limit = $limiter->consume();

        $headers = [
            'X-RateLimit-Remaining' => $limit->getRemainingTokens(),
            'X-RateLimit-Retry-After' => $limit->getRetryAfter()->getTimestamp(),
            'X-RateLimit-Limit' => $limit->getLimit(),
        ];

        if (false === $limit->isAccepted()) {
            throw new TooManyRequestsHttpException(headers: $headers);
        }

        return $headers;
    }

    /**
     * Logs timestamp, client, and route name of authenticated API access for admin
     * to track how API clients are being (ab)used and for stat creation.
     *
     * This might be better to have as a cache entry, with an aggregate in the database
     * created periodically
     */
    private function logAccess(): void
    {
        /** @var ?OAuth2Token $token */
        $token = $this->container->get('security.token_storage')->getToken();
        if (null !== $token && $token instanceof OAuth2Token) {
            $clientId = $token->getOAuthClientId();
            /** @var Client $client */
            $client = $this->entityManager->getReference(Client::class, $clientId);
            $access = new OAuth2ClientAccess();
            $access->setClient($client);
            $access->setCreatedAt(new \DateTimeImmutable());
            $access->setPath($this->request->getCurrentRequest()->get('_route'));
            $this->clientAccessRepository->save($access, flush: true);
        }
    }

    public function getOAuthToken(): ?OAuth2Token
    {
        try {
            /** @var ?OAuth2Token $token */
            $token = $this->container->get('security.token_storage')->getToken();
            if ($token instanceof OAuth2Token) {
                return $token;
            }
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
            $this->logger->warning('there was an error getting the access token: {e} - {m}, {stack}', [
                'e' => \get_class($e),
                'm' => $e->getMessage(),
                'stack' => $e->getTraceAsString(),
            ]);
        }

        return null;
    }

    public function getAccessToken(?OAuth2Token $oAuth2Token): ?AccessToken
    {
        if (!$oAuth2Token) {
            return null;
        }

        return $this->entityManager
            ->getRepository(AccessToken::class)
            ->findOneBy(['identifier' => $oAuth2Token->getAttribute('access_token_id')]);
    }

    public function serializePaginated(array $serializedItems, PagerfantaInterface $pagerfanta): array
    {
        return [
            'items' => $serializedItems,
            'pagination' => new PaginationSchema($pagerfanta),
        ];
    }

    public function serializeContentInterface(ContentInterface $content, bool $forceVisible = false): mixed
    {
        $toReturn = null;
        if ($content instanceof Entry) {
            $cross = $this->entryRepository->findCross($content);
            $crossDtos = array_map(fn ($entry) => $this->entryFactory->createResponseDto($entry, []), $cross);
            $dto = $this->entryFactory->createResponseDto($content, $this->tagLinkRepository->getTagsOfContent($content), $crossDtos);
            $dto->visibility = $forceVisible ? VisibilityInterface::VISIBILITY_VISIBLE : $dto->visibility;
            $toReturn = $dto->jsonSerialize();
            $toReturn['itemType'] = 'entry';
        } elseif ($content instanceof EntryComment) {
            $dto = $this->entryCommentFactory->createResponseDto($content, $this->tagLinkRepository->getTagsOfContent($content));
            $dto->visibility = $forceVisible ? VisibilityInterface::VISIBILITY_VISIBLE : $dto->visibility;
            $toReturn = $dto->jsonSerialize();
            $toReturn['itemType'] = 'entry_comment';
        } elseif ($content instanceof Post) {
            $dto = $this->postFactory->createResponseDto($content, $this->tagLinkRepository->getTagsOfContent($content));
            $dto->visibility = $forceVisible ? VisibilityInterface::VISIBILITY_VISIBLE : $dto->visibility;
            $toReturn = $dto->jsonSerialize();
            $toReturn['itemType'] = 'post';
        } elseif ($content instanceof PostComment) {
            $dto = $this->postCommentFactory->createResponseDto($content, $this->tagLinkRepository->getTagsOfContent($content));
            $dto->visibility = $forceVisible ? VisibilityInterface::VISIBILITY_VISIBLE : $dto->visibility;
            $toReturn = $dto->jsonSerialize();
            $toReturn['itemType'] = 'post_comment';
        } else {
            throw new \LogicException('Invalid contentInterface classname "'.$this->entityManager->getClassMetadata(\get_class($content))->rootEntityName.'"');
        }

        if ($forceVisible) {
            $toReturn['visibility'] = $content->visibility;
        }

        return $toReturn;
    }

    /**
     * Serialize a single log item to JSON.
     */
    protected function serializeLogItem(MagazineLog $log): array
    {
        /** @var ?ContentVisibilityInterface $subject */
        $subject = $log->getSubject();
        $response = $this->magazineFactory->createLogDto($log);
        $response->setSubject(
            $subject,
            $this->entryFactory,
            $this->entryCommentFactory,
            $this->postFactory,
            $this->postCommentFactory,
            $this->tagLinkRepository,
        );

        if ($response->subject) {
            $response->subject->visibility = 'visible';
        }

        $toReturn = $response->jsonSerialize();
        if ($subject) {
            if ($toReturn['subject'] instanceof \JsonSerializable) {
                $toReturn['subject'] = $toReturn['subject']->jsonSerialize();
            }

            $toReturn['subject']['visibility'] = $subject->getVisibility();
        }

        return $toReturn;
    }

    /**
     * Serialize a single magazine to JSON.
     *
     * @param MagazineDto $dto The MagazineDto to serialize
     *
     * @return MagazineResponseDto An associative array representation of the entry's safe fields, to be used as JSON
     */
    protected function serializeMagazine(MagazineDto $dto): MagazineResponseDto
    {
        $response = $this->magazineFactory->createResponseDto($dto);

        if ($user = $this->getUser()) {
            $response->notificationStatus = $this->notificationSettingsRepository->findOneByTarget($user, $dto)?->getStatus() ?? ENotificationStatus::Default;
        }

        return $response;
    }

    /**
     * Serialize a single user to JSON.
     *
     * @param UserDto $dto The UserDto to serialize
     *
     * @return UserResponseDto A JsonSerializable representation of the user
     */
    protected function serializeUser(UserDto $dto): UserResponseDto
    {
        $response = new UserResponseDto($dto);

        if ($user = $this->getUser()) {
            $response->notificationStatus = $this->notificationSettingsRepository->findOneByTarget($user, $dto)?->getStatus() ?? ENotificationStatus::Default;
        }

        return $response;
    }

    public static function constrainPerPage(mixed $value, int $min = self::MIN_PER_PAGE, int $max = self::MAX_PER_PAGE): int
    {
        return min(max(\intval($value), $min), $max);
    }

    /**
     * Alias for constrainPerPage with different defaults.
     */
    public static function constrainDepth(mixed $value, int $min = self::MIN_DEPTH, int $max = self::MAX_DEPTH): int
    {
        return self::constrainPerPage($value, $min, $max);
    }

    public function handleLanguageCriteria(Criteria $criteria): void
    {
        $usePreferred = filter_var($this->request->getCurrentRequest()->get('usePreferredLangs', false), FILTER_VALIDATE_BOOL);

        if ($usePreferred && null === $this->getUser()) {
            // Debating between AccessDenied and BadRequest exceptions for this
            throw new AccessDeniedHttpException('You must be logged in to use your preferred languages');
        }

        $languages = $usePreferred ? $this->getUserOrThrow()->preferredLanguages : $this->request->getCurrentRequest()->get('lang');
        if (null !== $languages) {
            if (\is_string($languages)) {
                $languages = explode(',', $languages);
            }

            $criteria->languages = $languages;
        }
    }

    /**
     * @throws BadRequestHttpException|\Exception
     */
    public function handleUploadedImage(): Image
    {
        $img = $this->handleUploadedImageOptional();
        if (null === $img) {
            throw new BadRequestHttpException('Uploaded file not found!');
        }

        return $img;
    }

    /**
     * @throws BadRequestHttpException|\Exception
     */
    public function handleUploadedImageOptional(): ?Image
    {
        try {
            /**
             * @var UploadedFile $uploaded
             */
            $uploaded = $this->request->getCurrentRequest()->files->get('uploadImage');

            if (null === $uploaded) {
                return null;
            }

            if (null === self::$constraint) {
                self::$constraint = ImageConstraint::default();
            }

            if (self::$constraint->maxSize < $uploaded->getSize()) {
                throw new BadRequestHttpException('File cannot exceed '.(string) self::$constraint->maxSize.' bytes');
            }

            if (false === array_search($uploaded->getMimeType(), self::$constraint->mimeTypes)) {
                throw new BadRequestHttpException('Mimetype of "'.$uploaded->getMimeType().'" not allowed!');
            }

            $image = $this->imageRepository->findOrCreateFromUpload($uploaded);

            if (null === $image) {
                throw new BadRequestHttpException('Failed to create file');
            }

            $image->altText = $this->request->getCurrentRequest()->get('alt', null);
        } catch (\Exception $e) {
            if (null !== $uploaded && file_exists($uploaded->getPathname())) {
                unlink($uploaded->getPathname());
            }
            throw $e;
        }

        return $image;
    }

    protected function reportContent(ReportInterface $reportable): void
    {
        /** @var ReportRequestDto $dto */
        $dto = $this->serializer->deserialize($this->request->getCurrentRequest()->getContent(), ReportRequestDto::class, 'json');

        $errors = $this->validator->validate($dto);
        if (0 < \count($errors)) {
            throw new BadRequestHttpException((string) $errors);
        }

        $reportDto = ReportDto::create($reportable, $dto->reason);

        try {
            $this->reportManager->report($reportDto, $this->getUserOrThrow());
        } catch (SubjectHasBeenReportedException $e) {
            // Do nothing
        }
    }

    /**
     * Serialize a single entry to JSON.
     *
     * @param Entry[]|null $crosspostedEntries
     */
    protected function serializeEntry(EntryDto|Entry $dto, array $tags, ?array $crosspostedEntries = null): EntryResponseDto
    {
        $crosspostedEntryDtos = null;
        if (null !== $crosspostedEntries) {
            $crosspostedEntryDtos = array_map(fn (Entry $item) => $this->entryFactory->createResponseDto($item, []), $crosspostedEntries);
        }
        $response = $this->entryFactory->createResponseDto($dto, $tags, $crosspostedEntryDtos);

        if ($this->isGranted('ROLE_OAUTH2_ENTRY:VOTE')) {
            $response->isFavourited = $dto instanceof EntryDto ? $dto->isFavourited : $dto->isFavored($this->getUserOrThrow());
            $response->userVote = $dto instanceof EntryDto ? $dto->userVote : $dto->getUserChoice($this->getUserOrThrow());
        }

        if ($user = $this->getUser()) {
            $response->canAuthUserModerate = $dto->getMagazine()->userIsModerator($user) || $user->isModerator() || $user->isAdmin();
            $response->notificationStatus = $this->notificationSettingsRepository->findOneByTarget($user, $dto)?->getStatus() ?? ENotificationStatus::Default;
        }

        return $response;
    }

    /**
     * Serialize a single entry comment to JSON.
     */
    protected function serializeEntryComment(EntryCommentDto $comment, array $tags): EntryCommentResponseDto
    {
        $response = $this->entryCommentFactory->createResponseDto($comment, $tags);

        if ($this->isGranted('ROLE_OAUTH2_ENTRY_COMMENT:VOTE')) {
            $response->isFavourited = $comment->isFavourited;
            $response->userVote = $comment->userVote;
        }

        if ($user = $this->getUser()) {
            $response->canAuthUserModerate = $comment->magazine->userIsModerator($user) || $user->isModerator() || $user->isAdmin();
        }

        return $response;
    }

    /**
     * Serialize a single post to JSON.
     */
    protected function serializePost(Post|PostDto $dto, array $tags): PostResponseDto
    {
        if (null === $dto) {
            return [];
        }
        $response = $this->postFactory->createResponseDto($dto, $tags);

        if ($this->isGranted('ROLE_OAUTH2_POST:VOTE')) {
            $response->isFavourited = $dto instanceof PostDto ? $dto->isFavourited : $dto->isFavored($this->getUserOrThrow());
            $response->userVote = $dto instanceof PostDto ? $dto->userVote : $dto->getUserChoice($this->getUserOrThrow());
        }

        if ($user = $this->getUser()) {
            $response->canAuthUserModerate = $dto->getMagazine()->userIsModerator($user) || $user->isModerator() || $user->isAdmin();
            $response->notificationStatus = $this->notificationSettingsRepository->findOneByTarget($user, $dto)?->getStatus() ?? ENotificationStatus::Default;
        }

        return $response;
    }

    /**
     * Serialize a single comment to JSON.
     */
    protected function serializePostComment(PostCommentDto $comment, array $tags): PostCommentResponseDto
    {
        $response = $this->postCommentFactory->createResponseDto($comment, $tags);

        if ($this->isGranted('ROLE_OAUTH2_POST_COMMENT:VOTE')) {
            $response->isFavourited = $comment instanceof PostCommentDto ? $comment->isFavourited : $comment->isFavored($this->getUserOrThrow());
            $response->userVote = $comment instanceof PostCommentDto ? $comment->userVote : $comment->getUserChoice($this->getUserOrThrow());
        }

        if ($user = $this->getUser()) {
            $response->canAuthUserModerate = $comment->getMagazine()->userIsModerator($user) || $user->isModerator() || $user->isAdmin();
        }

        return $response;
    }
}
