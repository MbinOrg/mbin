<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Entry\Comment;

use App\Tests\WebTestCase;

class EntryCommentReportApiTest extends WebTestCase
{
    public function testApiCannotReportCommentAnonymous(): void
    {
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry);

        $report = [
            'reason' => 'This comment breaks the rules!',
        ];

        $this->client->jsonRequest('POST', "/api/comments/{$comment->getId()}/report", $report);

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotReportCommentWithoutScope(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user);

        $report = [
            'reason' => 'This comment breaks the rules!',
        ];

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('POST', "/api/comments/{$comment->getId()}/report", $report, server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanReportOtherUsersComment(): void
    {
        $user = $this->getUserByUsername('user');
        $user2 = $this->getUserByUsername('other');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user2);

        $reportRepository = $this->reportRepository;

        $report = [
            'reason' => 'This comment breaks the rules!',
        ];

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read entry_comment:report');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('POST', "/api/comments/{$comment->getId()}/report", $report, server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(204);
        $report = $reportRepository->findBySubject($comment);
        self::assertNotNull($report);
        self::assertSame('This comment breaks the rules!', $report->reason);
        self::assertSame($user->getId(), $report->reporting->getId());
    }

    public function testApiCanReportOwnComment(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user);

        $reportRepository = $this->reportRepository;

        $report = [
            'reason' => 'This comment breaks the rules!',
        ];

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read entry_comment:report');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('POST', "/api/comments/{$comment->getId()}/report", $report, server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(204);
        $report = $reportRepository->findBySubject($comment);
        self::assertNotNull($report);
        self::assertSame('This comment breaks the rules!', $report->reason);
        self::assertSame($user->getId(), $report->reporting->getId());
    }
}
