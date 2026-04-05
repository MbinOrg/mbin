<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Poll;

use App\Tests\WebTestCase;
use PHPUnit\Framework\Attributes\Depends;

class PollVoteControllerTest extends WebTestCase
{
    public function testCannotVoteAnonymously(): void
    {
        $entry = $this->getEntryByTitle('Entry with poll');
        $entry->poll = $this->createSimplePoll(false, false);
        $this->entityManager->flush();

        $this->client->request('PUT', "/api/entry/{$entry->getId()}/poll/vote?choices[]=B");
        self::assertResponseStatusCodeSame(401);
    }

    public function testCannotVoteWithRead(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('Entry with poll');
        $entry->poll = $this->createSimplePoll(false, false);
        $this->entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/entry/{$entry->getId()}/poll/vote?choices[]=B", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testCannotVoteWithWrongChoice(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('Entry with poll');
        $entry->poll = $this->createSimplePoll(false, false);
        $this->entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'entry:vote');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/entry/{$entry->getId()}/poll/vote?choices[]=D", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testCannotVoteOnExpiredPoll(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('Entry with poll');
        $entry->poll = $this->createSimplePoll(false, false);
        $entry->poll->endDate = new \DateTimeImmutable('now - 1 day');
        $this->entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'entry:vote');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/entry/{$entry->getId()}/poll/vote?choices[]=A", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testVoteOnEntryPoll(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('Entry with poll');
        $entry->poll = $this->createSimplePoll(false, false);
        $this->entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'entry:vote');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/entry/{$entry->getId()}/poll/vote?choices[]=B", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $this->verifyPollResponse(['B']);
    }

    #[Depends('testVoteOnEntryPoll')]
    public function testCannotVoteTwice(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('Entry with poll');
        $entry->poll = $this->createSimplePoll(false, false);
        $this->entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'entry:vote');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/entry/{$entry->getId()}/poll/vote?choices[]=B", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $this->verifyPollResponse(['B']);

        $this->client->request('PUT', "/api/entry/{$entry->getId()}/poll/vote?choices[]=A", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testVoteOnEntryMultipleChoicePoll(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('Entry with multiple choice poll');
        $entry->poll = $this->createSimplePoll(true, false);
        $this->entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'entry:vote');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/entry/{$entry->getId()}/poll/vote?choices[]=B&choices[]=C", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $this->verifyPollResponse(['B', 'C']);
    }

    public function testVoteOnEntryCommentPoll(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('Entry with comment');
        $entryComment = $this->createEntryComment('comment with poll', $entry);
        $entryComment->poll = $this->createSimplePoll(false, false);
        $this->entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'entry_comment:vote');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/entry/{$entry->getId()}/comments/{$entryComment->getId()}/poll/vote?choices[]=B", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $this->verifyPollResponse(['B']);
    }

    public function testVoteOnEntryCommentMultipleChoicePoll(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('Entry with comment');
        $entryComment = $this->createEntryComment('comment with poll', $entry);
        $entryComment->poll = $this->createSimplePoll(true, false);
        $this->entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'entry_comment:vote');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/entry/{$entry->getId()}/comments/{$entryComment->getId()}/poll/vote?choices[]=B&choices[]=A", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $this->verifyPollResponse(['B', 'A']);
    }

    public function testVoteOnPostPoll(): void
    {
        $user = $this->getUserByUsername('user');
        $post = $this->createPost('Post with poll');
        $post->poll = $this->createSimplePoll(false, false);
        $this->entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'post:vote');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/post/{$post->getId()}/poll/vote?choices[]=B", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $this->verifyPollResponse(['B']);
    }

    public function testVoteOnPostMultipleChoicePoll(): void
    {
        $user = $this->getUserByUsername('user');
        $post = $this->createPost('Post with poll');
        $post->poll = $this->createSimplePoll(true, false);
        $this->entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'post:vote');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/post/{$post->getId()}/poll/vote?choices[]=B&choices[]=A&choices[]=C", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $this->verifyPollResponse(['B', 'A', 'C']);
    }

    public function testVoteOnPostCommentPoll(): void
    {
        $user = $this->getUserByUsername('user');
        $post = $this->createPost('Post with comment');
        $postComment = $this->createPostComment('comment with poll', $post);
        $postComment->poll = $this->createSimplePoll(false, false);
        $this->entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'post_comment:vote');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/post/{$post->getId()}/comments/{$postComment->getId()}/poll/vote?choices[]=B", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $this->verifyPollResponse(['B']);
    }

    public function testVoteOnPostCommentMultipleChoicePoll(): void
    {
        $user = $this->getUserByUsername('user');
        $post = $this->createPost('Post with comment');
        $postComment = $this->createPostComment('comment with poll', $post);
        $postComment->poll = $this->createSimplePoll(true, false);
        $this->entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'post_comment:vote');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/post/{$post->getId()}/comments/{$postComment->getId()}/poll/vote?choices[]=B", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $this->verifyPollResponse(['B']);
    }

    private function verifyPollResponse(array $choices): void
    {
        $data = self::getJsonResponse($this->client);
        self::assertArrayKeysMatch(WebTestCase::POLL_KEYS, $data);
        self::assertIsArray($data['choices']);
        self::assertCount(3, $data['choices']);
        foreach ($data['choices'] as $choice) {
            self::assertArrayKeysMatch(WebTestCase::POLL_CHOICE_KEYS, $choice);
            if (\in_array($choice['name'], $choices)) {
                self::assertEquals(1, $choice['voteCount']);
                self::assertTrue($choice['currentUserHasVoted']);
            } else {
                self::assertEquals(0, $choice['voteCount']);
                self::assertFalse($choice['currentUserHasVoted']);
            }
        }
    }
}
