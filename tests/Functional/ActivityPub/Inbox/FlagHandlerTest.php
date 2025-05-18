<?php

declare(strict_types=1);

namespace App\Tests\Functional\ActivityPub\Inbox;

use App\DTO\ReportDto;
use App\Entity\Contracts\ReportInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Message\ActivityPub\Inbox\ActivityMessage;
use App\Tests\Functional\ActivityPub\ActivityPubFunctionalTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group(name: 'ActivityPub')]
#[Group(name: 'NonThreadSafe')]
class FlagHandlerTest extends ActivityPubFunctionalTestCase
{
    public const REASON = 'Some reason';
    private array $announceEntry;
    private array $flagAnnounceEntry;
    private array $announceEntryComment;
    private array $flagAnnounceEntryComment;
    private array $announcePost;
    private array $flagAnnouncePost;
    private array $announcePostComment;
    private array $flagAnnouncePostComment;
    private array $createEntry;
    private array $flagCreateEntry;
    private array $createEntryComment;
    private array $flagCreateEntryComment;
    private array $createPost;
    private array $flagCreatePost;
    private array $createPostComment;
    private array $flagCreatePostComment;

    public function testFlagRemoteEntryInRemoteMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announceEntry)));
        $subject = $this->entryRepository->findOneBy(['apId' => $this->announceEntry['object']['object']['id']]);
        self::assertNotNull($subject);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->flagAnnounceEntry)));
        $report = $this->reportRepository->findBySubject($subject);
        self::assertNotNull($report);
        self::assertSame($this->remoteSubscriber->username, $report->reporting->username);
        self::assertSame(self::REASON, $report->reason);
    }

    public function testFlagRemoteEntryCommentInRemoteMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announceEntry)));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announceEntryComment)));
        $subject = $this->entryCommentRepository->findOneBy(['apId' => $this->announceEntryComment['object']['object']['id']]);
        self::assertNotNull($subject);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->flagAnnounceEntryComment)));
        $report = $this->reportRepository->findBySubject($subject);
        self::assertNotNull($report);
        self::assertSame($this->remoteSubscriber->username, $report->reporting->username);
        self::assertSame(self::REASON, $report->reason);
    }

    public function testFlagRemotePostInRemoteMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announcePost)));
        $subject = $this->postRepository->findOneBy(['apId' => $this->announcePost['object']['object']['id']]);
        self::assertNotNull($subject);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->flagAnnouncePost)));
        $report = $this->reportRepository->findBySubject($subject);
        self::assertNotNull($report);
        self::assertSame($this->remoteSubscriber->username, $report->reporting->username);
        self::assertSame(self::REASON, $report->reason);
    }

    public function testFlagRemotePostCommentInRemoteMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announcePost)));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announcePostComment)));
        $subject = $this->postCommentRepository->findOneBy(['apId' => $this->announcePostComment['object']['object']['id']]);
        self::assertNotNull($subject);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->flagAnnouncePostComment)));
        $report = $this->reportRepository->findBySubject($subject);
        self::assertNotNull($report);
        self::assertSame($this->remoteSubscriber->username, $report->reporting->username);
        self::assertSame(self::REASON, $report->reason);
    }

    public function testFlagRemoteEntryInLocalMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createEntry)));
        $subject = $this->entryRepository->findOneBy(['apId' => $this->createEntry['object']['id']]);
        self::assertNotNull($subject);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->flagCreateEntry)));
        $report = $this->reportRepository->findBySubject($subject);
        self::assertNotNull($report);
        self::assertSame($this->remoteSubscriber->username, $report->reporting->username);
        self::assertSame(self::REASON, $report->reason);
    }

    public function testFlagRemoteEntryCommentInLocalMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createEntry)));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createEntryComment)));
        $subject = $this->entryCommentRepository->findOneBy(['apId' => $this->createEntryComment['object']['id']]);
        self::assertNotNull($subject);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->flagCreateEntryComment)));
        $report = $this->reportRepository->findBySubject($subject);
        self::assertNotNull($report);
        self::assertSame($this->remoteSubscriber->username, $report->reporting->username);
        self::assertSame(self::REASON, $report->reason);
    }

    public function testFlagRemotePostInLocalMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createPost)));
        $subject = $this->postRepository->findOneBy(['apId' => $this->createPost['object']['id']]);
        self::assertNotNull($subject);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->flagCreatePost)));
        $report = $this->reportRepository->findBySubject($subject);
        self::assertNotNull($report);
        self::assertSame($this->remoteSubscriber->username, $report->reporting->username);
        self::assertSame(self::REASON, $report->reason);
    }

    public function testFlagRemotePostCommentInLocalMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createPost)));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createPostComment)));
        $subject = $this->postCommentRepository->findOneBy(['apId' => $this->createPostComment['object']['id']]);
        self::assertNotNull($subject);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->flagCreatePostComment)));
        $report = $this->reportRepository->findBySubject($subject);
        self::assertNotNull($report);
        self::assertSame($this->remoteSubscriber->username, $report->reporting->username);
        self::assertSame(self::REASON, $report->reason);
    }

    public function setUpRemoteEntities(): void
    {
        $this->announceEntry = $this->createRemoteEntryInRemoteMagazine($this->remoteMagazine, $this->remoteUser, fn (Entry $entry) => $this->buildFlagRemoteEntryInRemoteMagazine($entry));
        $this->announceEntryComment = $this->createRemoteEntryCommentInRemoteMagazine($this->remoteMagazine, $this->remoteUser, fn (EntryComment $comment) => $this->buildFlagRemoteEntryCommentInRemoteMagazine($comment));
        $this->announcePost = $this->createRemotePostInRemoteMagazine($this->remoteMagazine, $this->remoteUser, fn (Post $post) => $this->buildFlagRemotePostInRemoteMagazine($post));
        $this->announcePostComment = $this->createRemotePostCommentInRemoteMagazine($this->remoteMagazine, $this->remoteUser, fn (PostComment $comment) => $this->buildFlagRemotePostCommentInRemoteMagazine($comment));
        $this->createEntry = $this->createRemoteEntryInLocalMagazine($this->localMagazine, $this->remoteUser, fn (Entry $entry) => $this->buildFlagRemoteEntryInLocalMagazine($entry));
        $this->createEntryComment = $this->createRemoteEntryCommentInLocalMagazine($this->localMagazine, $this->remoteUser, fn (EntryComment $comment) => $this->buildFlagRemoteEntryCommentInLocalMagazine($comment));
        $this->createPost = $this->createRemotePostInLocalMagazine($this->localMagazine, $this->remoteUser, fn (Post $post) => $this->buildFlagRemotePostInLocalMagazine($post));
        $this->createPostComment = $this->createRemotePostCommentInLocalMagazine($this->localMagazine, $this->remoteUser, fn (PostComment $comment) => $this->buildFlagRemotePostCommentInLocalMagazine($comment));
    }

    private function buildFlagRemoteEntryInRemoteMagazine(Entry $entry): void
    {
        $this->flagAnnounceEntry = $this->createFlagActivity($this->remoteSubscriber, $entry);
    }

    private function buildFlagRemoteEntryCommentInRemoteMagazine(EntryComment $comment): void
    {
        $this->flagAnnounceEntryComment = $this->createFlagActivity($this->remoteSubscriber, $comment);
    }

    private function buildFlagRemotePostInRemoteMagazine(Post $post): void
    {
        $this->flagAnnouncePost = $this->createFlagActivity($this->remoteSubscriber, $post);
    }

    private function buildFlagRemotePostCommentInRemoteMagazine(PostComment $comment): void
    {
        $this->flagAnnouncePostComment = $this->createFlagActivity($this->remoteSubscriber, $comment);
    }

    private function buildFlagRemoteEntryInLocalMagazine(Entry $entry): void
    {
        $this->flagCreateEntry = $this->createFlagActivity($this->remoteSubscriber, $entry);
    }

    private function buildFlagRemoteEntryCommentInLocalMagazine(EntryComment $comment): void
    {
        $this->flagCreateEntryComment = $this->createFlagActivity($this->remoteSubscriber, $comment);
    }

    private function buildFlagRemotePostInLocalMagazine(Post $post): void
    {
        $this->flagCreatePost = $this->createFlagActivity($this->remoteSubscriber, $post);
    }

    private function buildFlagRemotePostCommentInLocalMagazine(PostComment $comment): void
    {
        $this->flagCreatePostComment = $this->createFlagActivity($this->remoteSubscriber, $comment);
    }

    private function createFlagActivity(Magazine|User $user, ReportInterface $subject): array
    {
        $dto = new ReportDto();
        $dto->subject = $subject;
        $dto->reason = self::REASON;
        $report = $this->reportManager->report($dto, $user);
        $flagActivity = $this->flagFactory->build($report);
        $flagActivityJson = $this->activityJsonBuilder->buildActivityJson($flagActivity);
        $flagActivityJson['actor'] = str_replace($this->remoteDomain, $this->remoteSubDomain, $flagActivityJson['actor']);

        $this->testingApHttpClient->activityObjects[$flagActivityJson['id']] = $flagActivityJson;
        $this->entitiesToRemoveAfterSetup[] = $flagActivity;

        return $flagActivityJson;
    }
}
