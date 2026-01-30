<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\UserNoteDto;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Entity\UserPushSubscription;
use App\Form\UserNoteType;
use App\PageView\PostCommentPageView;
use App\Payloads\NotificationsCountResponsePayload;
use App\Payloads\PushNotification;
use App\Payloads\RegisterPushRequestPayload;
use App\Payloads\TestPushRequestPayload;
use App\Payloads\UnRegisterPushRequestPayload;
use App\Repository\Criteria;
use App\Repository\EntryRepository;
use App\Repository\PostCommentRepository;
use App\Repository\UserPushSubscriptionRepository;
use App\Repository\UserRepository;
use App\Service\Notification\UserPushSubscriptionManager;
use App\Service\SettingsManager;
use App\Service\UserNoteManager;
use App\Utils\Embed;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Emoji\EmojiTransliterator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AjaxController extends AbstractController
{
    public function __construct(
        private readonly UserPushSubscriptionRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly UserPushSubscriptionManager $pushSubscriptionManager,
        private readonly TranslatorInterface $translator,
        private readonly SettingsManager $settingsManager,
        private readonly Security $security,
    ) {
    }

    public function fetchTitle(Embed $embed, Request $request): JsonResponse
    {
        $url = json_decode($request->getContent())->url;
        $embed = $embed->fetch($url);

        return new JsonResponse(
            [
                'title' => $embed->title,
                'description' => $embed->description,
                'image' => $embed->image,
            ]
        );
    }

    public function fetchDuplicates(EntryRepository $repository, Request $request): JsonResponse
    {
        $url = json_decode($request->getContent())->url;
        $entries = $repository->findBy(['url' => $url]);

        return new JsonResponse(
            [
                'total' => \count($entries),
                'html' => $this->renderView('entry/_list.html.twig', ['entries' => $entries]),
            ]
        );
    }

    /**
     * Returns an embeded objects html value, to be used for front-end insertion.
     */
    public function fetchEmbed(Embed $embed, Request $request): JsonResponse
    {
        $data = $embed->fetch($request->get('url'));
        // only wrap embed link for image embed as it doesn't make much sense for any other type for embed
        if ($data->isImageUrl()) {
            $html = \sprintf(
                '<a href="%s" class="embed-link">%s</a>',
                $data->url,
                $data->html
            );
        } else {
            $html = $data->html;
        }

        return new JsonResponse(
            [
                'html' => \sprintf('<div class="preview">%s</div>', $html),
            ]
        );
    }

    public function fetchEntry(Entry $entry, Request $request): JsonResponse
    {
        return new JsonResponse(
            [
                'html' => $this->renderView(
                    'components/_ajax.html.twig',
                    [
                        'component' => 'entry',
                        'attributes' => [
                            'entry' => $entry,
                        ],
                    ]
                ),
            ]
        );
    }

    public function fetchEntryComment(EntryComment $comment): JsonResponse
    {
        return new JsonResponse(
            [
                'html' => $this->renderView(
                    'components/_ajax.html.twig',
                    [
                        'component' => 'entry_comment',
                        'attributes' => [
                            'comment' => $comment,
                            'showEntryTitle' => false,
                            'showMagazineName' => false,
                        ],
                    ]
                ),
            ]
        );
    }

    public function fetchPost(Post $post): JsonResponse
    {
        return new JsonResponse(
            [
                'html' => $this->renderView(
                    'components/_ajax.html.twig',
                    [
                        'component' => 'post',
                        'attributes' => [
                            'post' => $post,
                        ],
                    ]
                ),
            ]
        );
    }

    public function fetchPostComment(PostComment $comment): JsonResponse
    {
        return new JsonResponse(
            [
                'html' => $this->renderView(
                    'components/_ajax.html.twig',
                    [
                        'component' => 'post_comment',
                        'attributes' => [
                            'comment' => $comment,
                        ],
                    ]
                ),
            ]
        );
    }

    public function fetchPostComments(Post $post, PostCommentRepository $repository): JsonResponse
    {
        $criteria = new PostCommentPageView(1, $this->security);
        $criteria->post = $post;
        $criteria->sortOption = Criteria::SORT_OLD;
        $criteria->perPage = 500;

        $comments = $repository->findByCriteria($criteria);

        return new JsonResponse(
            [
                'html' => $this->renderView(
                    'post/comment/_preview.html.twig',
                    ['comments' => $comments, 'post' => $post, 'criteria' => $criteria]
                ),
            ]
        );
    }

    public function fetchOnline(
        string $topic,
        string $mercurePublicUrl,
        string $mercureSubscriptionsToken,
        HttpClientInterface $httpClient,
        CacheInterface $cache,
    ): JsonResponse {
        $resp = $httpClient->request('GET', $mercurePublicUrl.'/subscriptions/'.$topic, [
            'auth_bearer' => $mercureSubscriptionsToken,
        ]);

        // @todo cloudflare bug
        $online = $cache->get($topic, function (ItemInterface $item) use ($resp) {
            $item->expiresAfter(45);

            return \count($resp->toArray()['subscriptions']) + 1;
        });

        return new JsonResponse([
            'online' => $online,
        ]);
    }

    public function fetchUserPopup(User $user, UserNoteManager $manager): JsonResponse
    {
        if ($this->getUser()) {
            $dto = $manager->createDto($this->getUserOrThrow(), $user);
        } else {
            $dto = new UserNoteDto();
            $dto->target = $user;
        }

        $form = $this->createForm(UserNoteType::class, $dto, [
            'action' => $this->generateUrl('user_note', ['username' => $dto->target->username]),
        ]);

        return new JsonResponse([
            'html' => $this->renderView('user/_user_popover.html.twig', ['user' => $user, 'form' => $form->createView()]
            ),
        ]);
    }

    public function fetchUsersSuggestions(string $username, Request $request, UserRepository $repository): JsonResponse
    {
        return new JsonResponse(
            [
                'html' => $this->renderView(
                    'search/_user_suggestion.html.twig',
                    [
                        'users' => $repository->findUsersSuggestions(ltrim($username, '@')),
                    ]
                ),
            ]
        );
    }

    public function fetchEmojiSuggestions(#[MapQueryParameter] string $query): JsonResponse
    {
        $trans = EmojiTransliterator::create('text-emoji');
        $class = new \ReflectionClass($trans);
        $emojis = $class->getProperty('map')->getValue($trans);
        $codes = array_keys($emojis);
        $matches = array_filter($codes, fn ($emoji) => str_contains($emoji, $query));
        $results = array_map(function ($code) use ($emojis) {
            $std = new \stdClass();
            $std->shortCode = $code;
            $std->emoji = $emojis[$code];

            return $std;
        }, $matches);

        return new JsonResponse(
            [
                'html' => $this->renderView(
                    'search/_emoji_suggestion.html.twig',
                    [
                        'emojis' => \array_slice($results, 0, 5),
                    ]
                ),
            ]
        );
    }

    #[IsGranted('ROLE_USER')]
    public function fetchNotificationsCount(): JsonResponse
    {
        $user = $this->getUserOrThrow();

        return new JsonResponse(new NotificationsCountResponsePayload($user->countNewNotifications(), $user->countNewMessages()));
    }

    public function registerPushNotifications(#[MapRequestPayload] RegisterPushRequestPayload $payload): JsonResponse
    {
        $user = $this->getUserOrThrow();
        $pushSubscription = $this->repository->findOneBy(['apiToken' => null, 'deviceKey' => $payload->deviceKey, 'user' => $user]);
        if (!$pushSubscription) {
            $pushSubscription = new UserPushSubscription($user, $payload->endpoint, $payload->contentPublicKey, $payload->serverKey, []);
            $pushSubscription->deviceKey = $payload->deviceKey;
            $pushSubscription->locale = $this->settingsManager->getLocale();
        } else {
            $pushSubscription->endpoint = $payload->endpoint;
            $pushSubscription->serverAuthKey = $payload->serverKey;
            $pushSubscription->contentEncryptionPublicKey = $payload->contentPublicKey;
        }
        $this->entityManager->persist($pushSubscription);
        $this->entityManager->flush();

        try {
            $testNotification = new PushNotification(null, '', $this->translator->trans('test_push_message', locale: $pushSubscription->locale));
            $this->pushSubscriptionManager->sendTextToUser($user, $testNotification, specificDeviceKey: $payload->deviceKey);

            return new JsonResponse();
        } catch (\ErrorException $e) {
            $this->logger->error('[AjaxController::handle] There was an exception while deleting a UserPushSubscription: {e} - {m}. {o}', [
                'e' => \get_class($e),
                'm' => $e->getMessage(),
                'o' => json_encode($e),
            ]);

            return new JsonResponse(status: 500);
        }
    }

    public function unregisterPushNotifications(#[MapRequestPayload] UnRegisterPushRequestPayload $payload): JsonResponse
    {
        try {
            $conn = $this->entityManager->getConnection();
            $stmt = $conn->prepare('DELETE FROM user_push_subscription WHERE user_id = :user AND device_key = :device');
            $stmt->executeQuery(['user' => $this->getUserOrThrow()->getId(), 'device' => $payload->deviceKey]);

            return new JsonResponse();
        } catch (\Exception $e) {
            $this->logger->error('[AjaxController::unregisterPushNotifications] There was an exception while deleting a UserPushSubscription: {e} - {m}. {o}', [
                'e' => \get_class($e),
                'm' => $e->getMessage(),
                'o' => json_encode($e),
            ]);

            return new JsonResponse(status: 500);
        }
    }

    public function testPushNotification(#[MapRequestPayload] TestPushRequestPayload $payload): JsonResponse
    {
        $user = $this->getUserOrThrow();
        try {
            $this->pushSubscriptionManager->sendTextToUser($user, new PushNotification(null, '', $this->translator->trans('test_push_message')), specificDeviceKey: $payload->deviceKey);

            return new JsonResponse();
        } catch (\ErrorException $e) {
            $this->logger->error('[AjaxController::testPushNotification] There was an exception while deleting a UserPushSubscription: {e} - {m}. {o}', [
                'e' => \get_class($e),
                'm' => $e->getMessage(),
                'o' => json_encode($e),
            ]);

            return new JsonResponse(status: 500);
        }
    }
}
