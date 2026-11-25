<?php

declare(strict_types=1);

namespace App\Tests\Functional\ActivityPub\Inbox;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Message\ActivityPub\Inbox\ActivityMessage;
use App\Tests\Functional\ActivityPub\ActivityPubFunctionalTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group(name: 'ActivityPub')]
#[Group(name: 'NonThreadSafe')]
class CreateHandlerPollTest extends ActivityPubFunctionalTestCase
{
    private Entry $localEntryWithPoll;
    private array $remoteVoteOnLocalPoll;
    private Entry $localEntryWithMultipleChoicePoll;
    private array $remoteVoteOnLocalMultipleChoicePoll;
    private EntryComment $localEntryCommentWithPoll;
    private array $remoteVoteOnLocalEntryCommentPoll;
    private EntryComment $localEntryCommentWithMultipleChoicePoll;
    private array $remoteVoteOnLocalEntryCommentMultipleChoicePoll;
    private Post $localPostWithPoll;
    private array $remoteVoteOnLocalPostPoll;
    private Post $localPostWithMultipleChoicePoll;
    private array $remoteVoteOnLocalPostMultipleChoicePoll;
    private PostComment $localPostCommentWithPoll;
    private array $remoteVoteOnLocalPostCommentPoll;
    private PostComment $localPostCommentWithMultipleChoicePoll;
    private array $remoteVoteOnLocalPostCommentMultipleChoicePoll;

    public function setUpRemoteEntities(): void
    {
    }

    public function setUpLocalEntities(): void
    {
        $this->setupRemoteActor();
    }

    public function testRemoteVoteOnLocalEntryWithPoll(): void
    {
        $this->localEntryWithPoll = $this->getEntryByTitle('A local entry with a poll');
        $this->localEntryWithPoll->poll = $this->createSimplePoll($this->localEntryWithPoll, false, false);
        $this->postSetupRunInRemoteDomain($this->remoteDomain, function () {
            $this->remoteVoteOnLocalPoll = $this->createRemoteVoteOnLocalPoll($this->localEntryWithPoll->poll, $this->remoteUser, 'B');
        });

        $this->bus->dispatch(new ActivityMessage(json_encode($this->remoteVoteOnLocalPoll)));
        $entry = $this->entryRepository->find($this->localEntryWithPoll->getId());
        self::assertNotNull($entry);
        self::assertNotNull($entry->poll);
        self::assertEquals(1, $entry->poll->voterCount);
        self::assertNotNull($entry->poll->findChoice('B'));
        self::assertEquals(1, $entry->poll->findChoice('B')->voteCount);
    }

    public function testRemoteVotesOnLocalEntryWithMultipleChoicePoll(): void
    {
        $this->localEntryWithMultipleChoicePoll = $this->getEntryByTitle('A local entry with a multiple choice poll');
        $this->localEntryWithMultipleChoicePoll->poll = $this->createSimplePoll($this->localEntryWithMultipleChoicePoll, true, false);
        $this->postSetupRunInRemoteDomain($this->remoteDomain, function () {
            $this->remoteVoteOnLocalMultipleChoicePoll = $this->createRemoteVoteOnLocalPoll($this->localEntryWithMultipleChoicePoll->poll, $this->remoteUser, 'A');
        });

        $choiceA = $this->remoteVoteOnLocalMultipleChoicePoll;
        $choiceB = $this->remoteVoteOnLocalMultipleChoicePoll;
        $choiceB['object']['name'] = 'B';
        $choiceC = $this->remoteVoteOnLocalMultipleChoicePoll;
        $choiceC['object']['name'] = 'C';
        $this->bus->dispatch(new ActivityMessage(json_encode($choiceA)));
        $this->bus->dispatch(new ActivityMessage(json_encode($choiceB)));
        $this->bus->dispatch(new ActivityMessage(json_encode($choiceC)));
        $entry = $this->entryRepository->find($this->localEntryWithMultipleChoicePoll->getId());
        self::assertNotNull($entry);
        self::assertNotNull($entry->poll);
        self::assertEquals(1, $entry->poll->voterCount);
        self::assertNotNull($entry->poll->findChoice('A'));
        self::assertEquals(1, $entry->poll->findChoice('A')->voteCount);
        self::assertNotNull($entry->poll->findChoice('B'));
        self::assertEquals(1, $entry->poll->findChoice('B')->voteCount);
        self::assertNotNull($entry->poll->findChoice('C'));
        self::assertEquals(1, $entry->poll->findChoice('C')->voteCount);
    }

    public function testRemoteVoteOnLocalEntryCommentWithPoll(): void
    {
        $this->localEntryCommentWithPoll = $this->createEntryComment('A local entry comment with a poll');
        $this->localEntryCommentWithPoll->poll = $this->createSimplePoll($this->localEntryCommentWithPoll, false, false);
        $this->postSetupRunInRemoteDomain($this->remoteDomain, function () {
            $this->remoteVoteOnLocalEntryCommentPoll = $this->createRemoteVoteOnLocalPoll($this->localEntryCommentWithPoll->poll, $this->remoteUser, 'B');
        });

        $this->bus->dispatch(new ActivityMessage(json_encode($this->remoteVoteOnLocalEntryCommentPoll)));
        $entryComment = $this->entryCommentRepository->find($this->localEntryCommentWithPoll->getId());
        self::assertNotNull($entryComment);
        self::assertNotNull($entryComment->poll);
        self::assertEquals(1, $entryComment->poll->voterCount);
        self::assertNotNull($entryComment->poll->findChoice('B'));
        self::assertEquals(1, $entryComment->poll->findChoice('B')->voteCount);
    }

    public function testRemoteVotesOnLocalEntryCommentWithMultipleChoicePoll(): void
    {
        $this->localEntryCommentWithMultipleChoicePoll = $this->createEntryComment('A local entry comment with a multiple choice poll');
        $this->localEntryCommentWithMultipleChoicePoll->poll = $this->createSimplePoll($this->localEntryCommentWithMultipleChoicePoll, true, false);
        $this->postSetupRunInRemoteDomain($this->remoteDomain, function () {
            $this->remoteVoteOnLocalEntryCommentMultipleChoicePoll = $this->createRemoteVoteOnLocalPoll($this->localEntryCommentWithMultipleChoicePoll->poll, $this->remoteUser, 'A');
        });

        $choiceA = $this->remoteVoteOnLocalEntryCommentMultipleChoicePoll;
        $choiceB = $this->remoteVoteOnLocalEntryCommentMultipleChoicePoll;
        $choiceB['object']['name'] = 'B';
        $choiceC = $this->remoteVoteOnLocalEntryCommentMultipleChoicePoll;
        $choiceC['object']['name'] = 'C';
        $this->bus->dispatch(new ActivityMessage(json_encode($choiceA)));
        $this->bus->dispatch(new ActivityMessage(json_encode($choiceB)));
        $this->bus->dispatch(new ActivityMessage(json_encode($choiceC)));
        $entryComment = $this->entryCommentRepository->find($this->localEntryCommentWithMultipleChoicePoll->getId());
        self::assertNotNull($entryComment);
        self::assertNotNull($entryComment->poll);
        self::assertEquals(1, $entryComment->poll->voterCount);
        self::assertNotNull($entryComment->poll->findChoice('A'));
        self::assertEquals(1, $entryComment->poll->findChoice('A')->voteCount);
        self::assertNotNull($entryComment->poll->findChoice('B'));
        self::assertEquals(1, $entryComment->poll->findChoice('B')->voteCount);
        self::assertNotNull($entryComment->poll->findChoice('C'));
        self::assertEquals(1, $entryComment->poll->findChoice('C')->voteCount);
    }

    public function testRemoteVoteOnLocalPostWithPoll(): void
    {
        $this->localPostWithPoll = $this->createPost('A local post with a poll');
        $this->localPostWithPoll->poll = $this->createSimplePoll($this->localPostWithPoll, false, false);
        $this->postSetupRunInRemoteDomain($this->remoteDomain, function () {
            $this->remoteVoteOnLocalPostPoll = $this->createRemoteVoteOnLocalPoll($this->localPostWithPoll->poll, $this->remoteUser, 'B');
        });

        $this->bus->dispatch(new ActivityMessage(json_encode($this->remoteVoteOnLocalPostPoll)));
        $post = $this->postRepository->find($this->localPostWithPoll->getId());
        self::assertNotNull($post);
        self::assertNotNull($post->poll);
        self::assertEquals(1, $post->poll->voterCount);
        self::assertNotNull($post->poll->findChoice('B'));
        self::assertEquals(1, $post->poll->findChoice('B')->voteCount);
    }

    public function testRemoteVotesOnLocalPostWithMultipleChoicePoll(): void
    {
        $this->localPostWithMultipleChoicePoll = $this->createPost('A local post with a multiple choice poll');
        $this->localPostWithMultipleChoicePoll->poll = $this->createSimplePoll($this->localPostWithMultipleChoicePoll, true, false);
        $this->postSetupRunInRemoteDomain($this->remoteDomain, function () {
            $this->remoteVoteOnLocalPostMultipleChoicePoll = $this->createRemoteVoteOnLocalPoll($this->localPostWithMultipleChoicePoll->poll, $this->remoteUser, 'A');
        });

        $choiceA = $this->remoteVoteOnLocalPostMultipleChoicePoll;
        $choiceB = $this->remoteVoteOnLocalPostMultipleChoicePoll;
        $choiceB['object']['name'] = 'B';
        $choiceC = $this->remoteVoteOnLocalPostMultipleChoicePoll;
        $choiceC['object']['name'] = 'C';
        $this->bus->dispatch(new ActivityMessage(json_encode($choiceA)));
        $this->bus->dispatch(new ActivityMessage(json_encode($choiceB)));
        $this->bus->dispatch(new ActivityMessage(json_encode($choiceC)));
        $post = $this->postRepository->find($this->localPostWithMultipleChoicePoll->getId());
        self::assertNotNull($post);
        self::assertNotNull($post->poll);
        self::assertEquals(1, $post->poll->voterCount);
        self::assertNotNull($post->poll->findChoice('A'));
        self::assertEquals(1, $post->poll->findChoice('A')->voteCount);
        self::assertNotNull($post->poll->findChoice('B'));
        self::assertEquals(1, $post->poll->findChoice('B')->voteCount);
        self::assertNotNull($post->poll->findChoice('C'));
        self::assertEquals(1, $post->poll->findChoice('C')->voteCount);
    }

    public function testRemoteVoteOnLocalPostCommentWithPoll(): void
    {
        $this->localPostCommentWithPoll = $this->createPostComment('A local post comment with a poll');
        $this->localPostCommentWithPoll->poll = $this->createSimplePoll($this->localPostCommentWithPoll, false, false);
        $this->postSetupRunInRemoteDomain($this->remoteDomain, function () {
            $this->remoteVoteOnLocalPostCommentPoll = $this->createRemoteVoteOnLocalPoll($this->localPostCommentWithPoll->poll, $this->remoteUser, 'B');
        });

        $this->bus->dispatch(new ActivityMessage(json_encode($this->remoteVoteOnLocalPostCommentPoll)));
        $postComment = $this->postCommentRepository->find($this->localPostCommentWithPoll->getId());
        self::assertNotNull($postComment);
        self::assertNotNull($postComment->poll);
        self::assertEquals(1, $postComment->poll->voterCount);
        self::assertNotNull($postComment->poll->findChoice('B'));
        self::assertEquals(1, $postComment->poll->findChoice('B')->voteCount);
    }

    public function testRemoteVotesOnLocalPostCommentWithMultipleChoicePoll(): void
    {
        $this->localPostCommentWithMultipleChoicePoll = $this->createPostComment('A local post comment with a multiple choice poll');
        $this->localPostCommentWithMultipleChoicePoll->poll = $this->createSimplePoll($this->localPostCommentWithMultipleChoicePoll, true, false);
        $this->postSetupRunInRemoteDomain($this->remoteDomain, function () {
            $this->remoteVoteOnLocalPostCommentMultipleChoicePoll = $this->createRemoteVoteOnLocalPoll($this->localPostCommentWithMultipleChoicePoll->poll, $this->remoteUser, 'A');
        });

        $choiceA = $this->remoteVoteOnLocalPostCommentMultipleChoicePoll;
        $choiceB = $this->remoteVoteOnLocalPostCommentMultipleChoicePoll;
        $choiceB['object']['name'] = 'B';
        $choiceC = $this->remoteVoteOnLocalPostCommentMultipleChoicePoll;
        $choiceC['object']['name'] = 'C';
        $this->bus->dispatch(new ActivityMessage(json_encode($choiceA)));
        $this->bus->dispatch(new ActivityMessage(json_encode($choiceB)));
        $this->bus->dispatch(new ActivityMessage(json_encode($choiceC)));
        $postComment = $this->postCommentRepository->find($this->localPostCommentWithMultipleChoicePoll->getId());
        self::assertNotNull($postComment);
        self::assertNotNull($postComment->poll);
        self::assertEquals(1, $postComment->poll->voterCount);
        self::assertNotNull($postComment->poll->findChoice('A'));
        self::assertEquals(1, $postComment->poll->findChoice('A')->voteCount);
        self::assertNotNull($postComment->poll->findChoice('B'));
        self::assertEquals(1, $postComment->poll->findChoice('B')->voteCount);
        self::assertNotNull($postComment->poll->findChoice('C'));
        self::assertEquals(1, $postComment->poll->findChoice('C')->voteCount);
    }

    public function testRemoteVotesOnLocalEntryWithPollWithInvalidChoice(): void
    {
        $this->localEntryWithMultipleChoicePoll = $this->getEntryByTitle('A local entry with a multiple choice poll');
        $this->localEntryWithMultipleChoicePoll->poll = $this->createSimplePoll($this->localEntryWithMultipleChoicePoll, true, false);
        $this->postSetupRunInRemoteDomain($this->remoteDomain, function () {
            $this->remoteVoteOnLocalMultipleChoicePoll = $this->createRemoteVoteOnLocalPoll($this->localEntryWithMultipleChoicePoll->poll, $this->remoteUser, 'A');
        });

        $choiceA2 = $this->remoteVoteOnLocalMultipleChoicePoll;
        $choiceA2['object']['name'] = 'A2';
        $this->bus->dispatch(new ActivityMessage(json_encode($choiceA2)));
        $entry = $this->entryRepository->find($this->localEntryWithMultipleChoicePoll->getId());
        self::assertNotNull($entry);
        self::assertNotNull($entry->poll);
        self::assertEquals(0, $entry->poll->voterCount);
    }

    public function testRemoteVotesOnLocalEntryWithEndedPoll(): void
    {
        $this->localEntryWithMultipleChoicePoll = $this->getEntryByTitle('A local entry with a multiple choice poll');
        $this->localEntryWithMultipleChoicePoll->poll = $this->createSimplePoll($this->localEntryWithMultipleChoicePoll, true, false);
        $this->postSetupRunInRemoteDomain($this->remoteDomain, function () {
            $this->remoteVoteOnLocalMultipleChoicePoll = $this->createRemoteVoteOnLocalPoll($this->localEntryWithMultipleChoicePoll->poll, $this->remoteUser, 'A');
        });

        $entry = $this->entryRepository->find($this->localEntryWithMultipleChoicePoll->getId());
        self::assertNotNull($entry);
        self::assertNotNull($entry->poll);
        $entry->poll->endDate = new \DateTimeImmutable('-1 minute');
        $this->entityManager->flush();
        $choiceA = $this->remoteVoteOnLocalMultipleChoicePoll;
        $this->bus->dispatch(new ActivityMessage(json_encode($choiceA)));
        $entry = $this->entryRepository->find($this->localEntryWithMultipleChoicePoll->getId());
        self::assertNotNull($entry);
        self::assertNotNull($entry->poll);
        self::assertEquals(0, $entry->poll->voterCount);
    }

    public function testMultipleRemoteVotesOnLocalEntry(): void
    {
        $this->localEntryWithPoll = $this->getEntryByTitle('A local entry with a poll');
        $this->localEntryWithPoll->poll = $this->createSimplePoll($this->localEntryWithPoll, false, false);
        $this->postSetupRunInRemoteDomain($this->remoteDomain, function () {
            $this->remoteVoteOnLocalPoll = $this->createRemoteVoteOnLocalPoll($this->localEntryWithPoll->poll, $this->remoteUser, 'B');
        });

        $choiceA = $this->remoteVoteOnLocalPoll;
        $choiceA['object']['name'] = 'A';
        $this->bus->dispatch(new ActivityMessage(json_encode($choiceA)));

        $entry = $this->entryRepository->find($this->localEntryWithPoll->getId());
        self::assertNotNull($entry);
        self::assertNotNull($entry->poll);
        self::assertEquals(1, $entry->poll->voterCount);
        $choice = $entry->poll->findChoice('A');
        self::assertEquals(1, $choice->voteCount);

        $choiceB = $this->remoteVoteOnLocalPoll;
        $choiceB['object']['name'] = 'B';
        $this->bus->dispatch(new ActivityMessage(json_encode($choiceB)));

        $entry = $this->entryRepository->find($this->localEntryWithPoll->getId());
        self::assertNotNull($entry);
        self::assertNotNull($entry->poll);
        self::assertEquals(1, $entry->poll->voterCount);
        self::assertEquals(0, $entry->poll->findChoice('A')->voteCount);
        self::assertEquals(1, $entry->poll->findChoice('B')->voteCount);
    }

    private function setupRemoteActor(): void
    {
        $domain = 'some.instance.tld';
        $this->switchToRemoteDomain($domain);

        $user = $this->getUserByUsername('someOtherUser', addImage: false, email: 'user@some.tld');
        $this->registerActor($user, $domain, true);

        $user = $this->getUserByUsername('someUser', addImage: false, email: 'user2@some.tld');
        $this->registerActor($user, $domain, true);

        $magazine = $this->getMagazineByName('someMagazine', user: $user);
        $this->registerActor($magazine, $domain, true);

        $this->switchToLocalDomain();
    }
}
