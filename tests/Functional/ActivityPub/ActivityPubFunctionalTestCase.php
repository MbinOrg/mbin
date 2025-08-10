<?php

declare(strict_types=1);

namespace App\Tests\Functional\ActivityPub;

use App\ActivityPub\JsonRd;
use App\DTO\MessageDto;
use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Contracts\ActivityPubActorInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Entity\Message;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Event\ActivityPub\WebfingerResponseEvent;
use App\Service\ActivityPub\Webfinger\WebFingerFactory;
use App\Tests\ActivityPubTestCase;
use Doctrine\Common\Collections\ArrayCollection;

abstract class ActivityPubFunctionalTestCase extends ActivityPubTestCase
{
    protected Magazine $localMagazine;

    /**
     * @var ?Magazine This is only set during the setUp call
     */
    protected ?Magazine $remoteMagazine;

    protected User $localUser;

    /**
     * @var ?User This is only set during the setUp call
     */
    protected ?User $remoteUser;
    protected User $remoteSubscriber;
    protected ?string $prev;

    protected string $localDomain;
    protected string $remoteDomain = 'remote.mbin';
    protected string $remoteSubDomain = 'remote.sub.mbin';

    protected array $entitiesToRemoveAfterSetup = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->localDomain = $this->settingsManager->get('KBIN_DOMAIN');
        $this->setupLocalActors();

        $this->switchToRemoteDomain($this->remoteSubDomain);
        $this->setUpRemoteSubscriber();

        $this->entries = new ArrayCollection();
        $this->magazines = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->switchToLocalDomain();

        $this->switchToRemoteDomain($this->remoteDomain);

        $this->setUpRemoteActors();
        $this->setUpRemoteEntities();

        $this->entries = new ArrayCollection();
        $this->magazines = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->switchToLocalDomain();

        $this->setUpLocalEntities();

        $this->switchToRemoteDomain($this->remoteDomain);
        $this->setUpLateRemoteEntities();

        $this->switchToLocalDomain();

        // foreach ($this->entitiesToRemoveAfterSetup as $entity) {
        // $this->entityManager->remove($entity);
        // }

        for ($i = \sizeof($this->entitiesToRemoveAfterSetup) - 1; $i >= 0; --$i) {
            $this->entityManager->remove($this->entitiesToRemoveAfterSetup[$i]);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->remoteSubscriber = $this->activityPubManager->findActorOrCreate("@remoteSubscriber@$this->remoteSubDomain");
        $this->remoteSubscriber->publicKey = 'some public key';
        $this->remoteMagazine = $this->activityPubManager->findActorOrCreate("!remoteMagazine@$this->remoteDomain");
        $this->remoteMagazine->publicKey = 'some public key';
        $this->remoteUser = $this->activityPubManager->findActorOrCreate("@remoteUser@$this->remoteDomain");
        $this->remoteUser->publicKey = 'some public key';
        $this->localMagazine = $this->magazineRepository->findOneByName('magazine');
        $this->magazineManager->subscribe($this->localMagazine, $this->remoteSubscriber);
        self::assertTrue($this->localMagazine->isSubscribed($this->remoteSubscriber));
        $this->entityManager->refresh($this->localMagazine);
        $this->localUser = $this->userRepository->findOneByUsername('user');
    }

    protected function setupLocalActors(): void
    {
        $this->localUser = $this->getUserByUsername('user', addImage: false);
        $this->localMagazine = $this->getMagazineByName('magazine', user: $this->localUser);
        $this->entityManager->flush();
    }

    abstract public function setUpRemoteEntities(): void;

    /**
     * Override this method if you want to set up remote objects depending on you local entities.
     */
    public function setUpLateRemoteEntities(): void
    {
    }

    /**
     * Override this method if you want to set up additional local entities.
     */
    public function setUpLocalEntities(): void
    {
    }

    protected function setUpRemoteActors(): void
    {
        $domain = $this->remoteDomain;

        $username = 'remoteUser';
        $this->remoteUser = $this->getUserByUsername($username, addImage: false);

        $magazineName = 'remoteMagazine';
        $this->remoteMagazine = $this->getMagazineByName($magazineName, user: $this->remoteUser);

        $this->registerActor($this->remoteMagazine, $domain, true);
        $this->registerActor($this->remoteUser, $domain, true);
    }

    protected function setUpRemoteSubscriber(): void
    {
        $domain = $this->remoteSubDomain;
        $username = 'remoteSubscriber';
        $this->remoteSubscriber = $this->getUserByUsername($username, addImage: false);
        $this->registerActor($this->remoteSubscriber, $domain, true);
    }

    protected function registerActor(ActivityPubActorInterface $actor, string $domain, bool $remoteAfterSetup = false): void
    {
        if ($actor instanceof User) {
            $json = $this->personFactory->create($actor);
        } elseif ($actor instanceof Magazine) {
            $json = $this->groupFactory->create($actor);
        } else {
            $class = \get_class($actor);
            throw new \LogicException("tests do not support actors of type $class");
        }
        $this->testingApHttpClient->actorObjects[$json['id']] = $json;
        $username = $json['preferredUsername'];

        $userEvent = new WebfingerResponseEvent(new JsonRd(), "acct:$username@$domain", ['account' => $username]);
        $this->eventDispatcher->dispatch($userEvent);
        $realDomain = \sprintf(WebFingerFactory::WEBFINGER_URL, 'https', $domain, '', "$username@$domain");
        $this->testingApHttpClient->webfingerObjects[$realDomain] = $userEvent->jsonRd->toArray();

        if ($remoteAfterSetup) {
            $this->entitiesToRemoveAfterSetup[] = $actor;
        }
    }

    protected function switchToRemoteDomain($domain): void
    {
        $this->prev = $this->settingsManager->get('KBIN_DOMAIN');

        $this->settingsManager->set('KBIN_DOMAIN', $domain);
        $context = $this->router->getContext();
        $context->setHost($domain);
    }

    protected function switchToLocalDomain(): void
    {
        if (null === $this->prev) {
            return;
        }
        $context = $this->router->getContext();
        $this->settingsManager->set('KBIN_DOMAIN', $this->prev);
        $context->setHost($this->prev);
        $this->prev = null;
    }

    /**
     * @param callable(Entry $entry):void|null $entryCreateCallback
     */
    protected function createRemoteEntryInRemoteMagazine(Magazine $magazine, User $user, ?callable $entryCreateCallback = null): array
    {
        $entry = $this->getEntryByTitle('remote entry', magazine: $magazine, user: $user);
        $json = $this->pageFactory->create($entry, $this->tagLinkRepository->getTagsOfContent($entry));
        $this->testingApHttpClient->activityObjects[$json['id']] = $json;

        $createActivity = $this->createWrapper->build($entry);
        $create = $this->activityJsonBuilder->buildActivityJson($createActivity);
        $this->testingApHttpClient->activityObjects[$create['id']] = $create;

        $announceActivity = $this->announceWrapper->build($magazine, $createActivity);
        $announce = $this->activityJsonBuilder->buildActivityJson($announceActivity);
        $this->testingApHttpClient->activityObjects[$announce['id']] = $announce;

        if (null !== $entryCreateCallback) {
            $entryCreateCallback($entry);
        }

        $this->entitiesToRemoveAfterSetup[] = $announceActivity;
        $this->entitiesToRemoveAfterSetup[] = $createActivity;
        $this->entitiesToRemoveAfterSetup[] = $entry;

        return $announce;
    }

    /**
     * @param callable(EntryComment $entry):void|null $entryCommentCreateCallback
     */
    protected function createRemoteEntryCommentInRemoteMagazine(Magazine $magazine, User $user, ?callable $entryCommentCreateCallback = null): array
    {
        $entries = array_filter($this->entitiesToRemoveAfterSetup, fn ($item) => $item instanceof Entry);
        $entry = $entries[array_key_first($entries)];
        $comment = $this->createEntryComment('remote entry comment', $entry, $user);
        $json = $this->entryCommentNoteFactory->create($comment, $this->tagLinkRepository->getTagsOfContent($comment));
        $this->testingApHttpClient->activityObjects[$json['id']] = $json;

        $createActivity = $this->createWrapper->build($comment);
        $create = $this->activityJsonBuilder->buildActivityJson($createActivity);
        $this->testingApHttpClient->activityObjects[$create['id']] = $create;

        $announceActivity = $this->announceWrapper->build($magazine, $createActivity);
        $announce = $this->activityJsonBuilder->buildActivityJson($announceActivity);
        $this->testingApHttpClient->activityObjects[$announce['id']] = $announce;

        if (null !== $entryCommentCreateCallback) {
            $entryCommentCreateCallback($comment);
        }

        $this->entitiesToRemoveAfterSetup[] = $announceActivity;
        $this->entitiesToRemoveAfterSetup[] = $createActivity;
        $this->entitiesToRemoveAfterSetup[] = $comment;

        return $announce;
    }

    /**
     * @param callable(Post $entry):void|null $postCreateCallback
     */
    protected function createRemotePostInRemoteMagazine(Magazine $magazine, User $user, ?callable $postCreateCallback = null): array
    {
        $post = $this->createPost('remote post', magazine: $magazine, user: $user);
        $json = $this->postNoteFactory->create($post, $this->tagLinkRepository->getTagsOfContent($post));
        $this->testingApHttpClient->activityObjects[$json['id']] = $json;

        $createActivity = $this->createWrapper->build($post);
        $create = $this->activityJsonBuilder->buildActivityJson($createActivity);
        $this->testingApHttpClient->activityObjects[$create['id']] = $create;

        $announceActivity = $this->announceWrapper->build($magazine, $createActivity);
        $announce = $this->activityJsonBuilder->buildActivityJson($announceActivity);
        $this->testingApHttpClient->activityObjects[$announce['id']] = $announce;

        if (null !== $postCreateCallback) {
            $postCreateCallback($post);
        }

        $this->entitiesToRemoveAfterSetup[] = $announceActivity;
        $this->entitiesToRemoveAfterSetup[] = $createActivity;
        $this->entitiesToRemoveAfterSetup[] = $post;

        return $announce;
    }

    /**
     * @param callable(PostComment $entry):void|null $postCommentCreateCallback
     */
    protected function createRemotePostCommentInRemoteMagazine(Magazine $magazine, User $user, ?callable $postCommentCreateCallback = null): array
    {
        $posts = array_filter($this->entitiesToRemoveAfterSetup, fn ($item) => $item instanceof Post);
        $post = $posts[array_key_first($posts)];
        $comment = $this->createPostComment('remote post comment', $post, $user);
        $json = $this->postCommentNoteFactory->create($comment, $this->tagLinkRepository->getTagsOfContent($comment));
        $this->testingApHttpClient->activityObjects[$json['id']] = $json;

        $createActivity = $this->createWrapper->build($comment);
        $create = $this->activityJsonBuilder->buildActivityJson($createActivity);
        $this->testingApHttpClient->activityObjects[$create['id']] = $create;

        $announceActivity = $this->announceWrapper->build($magazine, $createActivity);
        $announce = $this->activityJsonBuilder->buildActivityJson($announceActivity);
        $this->testingApHttpClient->activityObjects[$announce['id']] = $announce;

        if (null !== $postCommentCreateCallback) {
            $postCommentCreateCallback($comment);
        }

        $this->entitiesToRemoveAfterSetup[] = $announceActivity;
        $this->entitiesToRemoveAfterSetup[] = $createActivity;
        $this->entitiesToRemoveAfterSetup[] = $comment;

        return $announce;
    }

    /**
     * @param callable(Entry $entry):void|null $entryCreateCallback
     */
    protected function createRemoteEntryInLocalMagazine(Magazine $magazine, User $user, ?callable $entryCreateCallback = null): array
    {
        $entry = $this->getEntryByTitle('remote entry in local', magazine: $magazine, user: $user);
        $json = $this->pageFactory->create($entry, $this->tagLinkRepository->getTagsOfContent($entry));
        $this->testingApHttpClient->activityObjects[$json['id']] = $json;

        $createActivity = $this->createWrapper->build($entry);
        $create = $this->activityJsonBuilder->buildActivityJson($createActivity);
        $this->testingApHttpClient->activityObjects[$create['id']] = $create;

        $create = $this->RewriteTargetFieldsToLocal($magazine, $create);

        if (null !== $entryCreateCallback) {
            $entryCreateCallback($entry);
        }

        $this->entitiesToRemoveAfterSetup[] = $createActivity;
        $this->entitiesToRemoveAfterSetup[] = $entry;

        return $create;
    }

    /**
     * @param callable(EntryComment $entry):void|null $entryCommentCreateCallback
     */
    protected function createRemoteEntryCommentInLocalMagazine(Magazine $magazine, User $user, ?callable $entryCommentCreateCallback = null): array
    {
        $entries = array_filter($this->entitiesToRemoveAfterSetup, fn ($item) => $item instanceof Entry && 'remote entry in local' === $item->title);
        $entry = $entries[array_key_first($entries)];
        $comment = $this->createEntryComment('remote entry comment', $entry, $user);
        $json = $this->entryCommentNoteFactory->create($comment, $this->tagLinkRepository->getTagsOfContent($comment));
        $this->testingApHttpClient->activityObjects[$json['id']] = $json;

        $createActivity = $this->createWrapper->build($comment);
        $create = $this->activityJsonBuilder->buildActivityJson($createActivity);
        $this->testingApHttpClient->activityObjects[$create['id']] = $create;

        $create = $this->RewriteTargetFieldsToLocal($magazine, $create);

        if (null !== $entryCommentCreateCallback) {
            $entryCommentCreateCallback($comment);
        }

        $this->entitiesToRemoveAfterSetup[] = $createActivity;
        $this->entitiesToRemoveAfterSetup[] = $comment;

        return $create;
    }

    /**
     * @param callable(Post $entry):void|null $postCreateCallback
     */
    protected function createRemotePostInLocalMagazine(Magazine $magazine, User $user, ?callable $postCreateCallback = null): array
    {
        $post = $this->createPost('remote post in local', magazine: $magazine, user: $user);
        $json = $this->postNoteFactory->create($post, $this->tagLinkRepository->getTagsOfContent($post));
        $this->testingApHttpClient->activityObjects[$json['id']] = $json;

        $createActivity = $this->createWrapper->build($post);
        $create = $this->activityJsonBuilder->buildActivityJson($createActivity);
        $this->testingApHttpClient->activityObjects[$create['id']] = $create;

        $create = $this->RewriteTargetFieldsToLocal($magazine, $create);

        if (null !== $postCreateCallback) {
            $postCreateCallback($post);
        }

        $this->entitiesToRemoveAfterSetup[] = $createActivity;
        $this->entitiesToRemoveAfterSetup[] = $post;

        return $create;
    }

    /**
     * @param callable(PostComment $entry):void|null $postCommentCreateCallback
     */
    protected function createRemotePostCommentInLocalMagazine(Magazine $magazine, User $user, ?callable $postCommentCreateCallback = null): array
    {
        $posts = array_filter($this->entitiesToRemoveAfterSetup, fn ($item) => $item instanceof Post && 'remote post in local' === $item->body);
        $post = $posts[array_key_first($posts)];
        $comment = $this->createPostComment('remote post comment in local', $post, $user);
        $json = $this->postCommentNoteFactory->create($comment, $this->tagLinkRepository->getTagsOfContent($comment));
        $this->testingApHttpClient->activityObjects[$json['id']] = $json;

        $createActivity = $this->createWrapper->build($comment);
        $create = $this->activityJsonBuilder->buildActivityJson($createActivity);
        $this->testingApHttpClient->activityObjects[$create['id']] = $create;

        $create = $this->RewriteTargetFieldsToLocal($magazine, $create);

        if (null !== $postCommentCreateCallback) {
            $postCommentCreateCallback($comment);
        }

        $this->entitiesToRemoveAfterSetup[] = $createActivity;
        $this->entitiesToRemoveAfterSetup[] = $comment;

        return $create;
    }

    /**
     * @param callable(Message $entry):void|null $messageCreateCallback
     */
    protected function createRemoteMessage(User $fromRemoteUser, User $toLocalUser, ?callable $messageCreateCallback = null): array
    {
        $dto = new MessageDto();
        $dto->body = 'remote message';
        $thread = $this->messageManager->toThread($dto, $fromRemoteUser, $toLocalUser);
        $message = $thread->getLastMessage();

        $this->entitiesToRemoveAfterSetup[] = $thread;
        $this->entitiesToRemoveAfterSetup[] = $message;

        $createActivity = $this->createWrapper->build($message);
        $create = $this->activityJsonBuilder->buildActivityJson($createActivity);
        $correctUserString = "https://$this->prev/u/$toLocalUser->username";
        $create['to'] = [$correctUserString];
        $create['object']['to'] = [$correctUserString];
        $this->testingApHttpClient->activityObjects[$create['id']] = $create;

        if (null !== $messageCreateCallback) {
            $messageCreateCallback($message);
        }

        $this->entitiesToRemoveAfterSetup[] = $createActivity;

        return $create;
    }

    /**
     * This rewrites the target fields `to` and `audience` to the @see self::$prev domain.
     * This is useful when remote actors create activities on local magazines.
     *
     * @return array the array with rewritten target fields
     */
    protected function RewriteTargetFieldsToLocal(Magazine $magazine, array $activityArray): array
    {
        $magazineAddress = "https://$this->prev/m/$magazine->name";
        $to = [
            $magazineAddress,
            ActivityPubActivityInterface::PUBLIC_URL,
        ];
        if (isset($activityArray['to'])) {
            $activityArray['to'] = $to;
        }
        if (isset($activityArray['audience'])) {
            $activityArray['audience'] = $magazineAddress;
        }
        if (isset($activityArray['object']) && \is_array($activityArray['object'])) {
            $activityArray['object'] = $this->RewriteTargetFieldsToLocal($magazine, $activityArray['object']);
        }

        return $activityArray;
    }

    protected function assertCountOfSentActivitiesOfType(int $expectedCount, string $type): void
    {
        $activities = $this->getSentActivitiesOfType($type);
        $this->assertCount($expectedCount, $activities);
    }

    protected function assertOneSentActivityOfType(string $type, ?string $activityId = null, ?string $inboxUrl = null): array
    {
        $activities = $this->getSentActivitiesOfType($type);
        self::assertCount(1, $activities);
        if (null !== $activityId) {
            self::assertEquals($activityId, $activities[0]['payload']['id']);
        }
        if (null !== $inboxUrl) {
            self::assertEquals($inboxUrl, $activities[0]['inboxUrl']);
        }

        return $activities[0]['payload'];
    }

    protected function assertOneSentAnnouncedActivityOfType(string $type, ?string $announcedActivityId = null): void
    {
        $activities = $this->getSentAnnounceActivitiesOfInnerType($type);
        self::assertCount(1, $activities);
        if (null !== $announcedActivityId) {
            self::assertEquals($announcedActivityId, $activities[0]['payload']['object']['id']);
        }
    }

    protected function assertOneSentAnnouncedActivityOfTypeGetInnerActivity(string $type, ?string $announcedActivityId = null, ?string $announceId = null, ?string $inboxUrl = null): array|string
    {
        $activities = $this->getSentAnnounceActivitiesOfInnerType($type);
        self::assertCount(1, $activities);
        if (null !== $announcedActivityId) {
            self::assertEquals($announcedActivityId, $activities[0]['payload']['object']['id']);
        }
        if (null !== $announceId) {
            self::assertEquals($announceId, $activities[0]['payload']['id']);
        }
        if (null !== $inboxUrl) {
            self::assertEquals($inboxUrl, $activities[0]['inboxUrl']);
        }

        return $activities[0]['payload']['object'];
    }

    /**
     * @return array<int, array{inboxUrl: string, payload: array, actor: User|Magazine}>
     */
    protected function getSentActivitiesOfType(string $type): array
    {
        return array_values(array_filter($this->testingApHttpClient->getPostedObjects(), fn (array $item) => $type === $item['payload']['type']));
    }

    /**
     * @return array<int, array{inboxUrl: string, payload: array, actor: User|Magazine}>
     */
    protected function getSentAnnounceActivitiesOfInnerType(string $type): array
    {
        return array_values(array_filter($this->testingApHttpClient->getPostedObjects(), fn (array $item) => 'Announce' === $item['payload']['type'] && $type === $item['payload']['object']['type']));
    }
}
