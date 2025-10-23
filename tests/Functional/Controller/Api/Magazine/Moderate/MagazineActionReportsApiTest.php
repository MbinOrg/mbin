<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Magazine\Moderate;

use App\DTO\ReportDto;
use App\Tests\Functional\Controller\Api\Magazine\MagazineRetrieveApiTest;
use App\Tests\WebTestCase;
use PHPUnit\Framework\Attributes\Group;

class MagazineActionReportsApiTest extends WebTestCase
{
    public function testApiCannotAcceptReportAnonymous(): void
    {
        $magazine = $this->getMagazineByName('test');
        $user = $this->getUserByUsername('JohnDoe');
        $reportedUser = $this->getUserByUsername('testuser');
        $entry = $this->getEntryByTitle('Report test', body: 'This is gonna be reported', magazine: $magazine, user: $reportedUser);

        $reportManager = $this->reportManager;
        $report = $reportManager->report(ReportDto::create($entry, 'I don\'t like it'), $user);
        $this->client->request('POST', "/api/moderate/magazine/{$magazine->getId()}/reports/{$report->getId()}/accept");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotRejectReportAnonymous(): void
    {
        $magazine = $this->getMagazineByName('test');
        $user = $this->getUserByUsername('JohnDoe');
        $reportedUser = $this->getUserByUsername('testuser');
        $entry = $this->getEntryByTitle('Report test', body: 'This is gonna be reported', magazine: $magazine, user: $reportedUser);

        $reportManager = $this->reportManager;
        $report = $reportManager->report(ReportDto::create($entry, 'I don\'t like it'), $user);
        $this->client->request('POST', "/api/moderate/magazine/{$magazine->getId()}/reports/{$report->getId()}/reject");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotAcceptReportWithoutScope(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();
        $magazine = $this->getMagazineByName('test');
        $reportedUser = $this->getUserByUsername('testuser');
        $entry = $this->getEntryByTitle('Report test', body: 'This is gonna be reported', magazine: $magazine, user: $reportedUser);

        $reportManager = $this->reportManager;
        $report = $reportManager->report(ReportDto::create($entry, 'I don\'t like it'), $user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('POST', "/api/moderate/magazine/{$magazine->getId()}/reports/{$report->getId()}/accept", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotRejectReportWithoutScope(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();
        $magazine = $this->getMagazineByName('test');
        $reportedUser = $this->getUserByUsername('testuser');
        $entry = $this->getEntryByTitle('Report test', body: 'This is gonna be reported', magazine: $magazine, user: $reportedUser);

        $reportManager = $this->reportManager;
        $report = $reportManager->report(ReportDto::create($entry, 'I don\'t like it'), $user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('POST', "/api/moderate/magazine/{$magazine->getId()}/reports/{$report->getId()}/reject", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotAcceptReportIfNotMod(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();
        $magazine = $this->getMagazineByName('test', $this->getUserByUsername('JaneDoe'));
        $reportedUser = $this->getUserByUsername('testuser');
        $entry = $this->getEntryByTitle('Report test', body: 'This is gonna be reported', magazine: $magazine, user: $reportedUser);

        $reportManager = $this->reportManager;
        $report = $reportManager->report(ReportDto::create($entry, 'I don\'t like it'), $user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine:reports:action');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('POST', "/api/moderate/magazine/{$magazine->getId()}/reports/{$report->getId()}/accept", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotRejectReportIfNotMod(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();
        $magazine = $this->getMagazineByName('test', $this->getUserByUsername('JaneDoe'));
        $reportedUser = $this->getUserByUsername('testuser');
        $entry = $this->getEntryByTitle('Report test', body: 'This is gonna be reported', magazine: $magazine, user: $reportedUser);

        $reportManager = $this->reportManager;
        $report = $reportManager->report(ReportDto::create($entry, 'I don\'t like it'), $user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine:reports:action');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('POST', "/api/moderate/magazine/{$magazine->getId()}/reports/{$report->getId()}/reject", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    #[Group(name: 'NonThreadSafe')]
    public function testApiCanAcceptReport(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();
        $magazine = $this->getMagazineByName('test');
        $reportedUser = $this->getUserByUsername('testuser');
        $entry = $this->getEntryByTitle('Report test', body: 'This is gonna be reported', magazine: $magazine, user: $reportedUser);

        $reportManager = $this->reportManager;
        $report = $reportManager->report(ReportDto::create($entry, 'I don\'t like it'), $user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine:reports:action');
        $token = $codes['token_type'].' '.$codes['access_token'];
        $consideredAt = new \DateTimeImmutable();

        $this->client->jsonRequest('POST', "/api/moderate/magazine/{$magazine->getId()}/reports/{$report->getId()}/accept", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(MagazineRetrieveReportsApiTest::REPORT_RESPONSE_KEYS, $jsonData);
        self::assertEquals('entry_report', $jsonData['type']);
        self::assertEquals($report->reason, $jsonData['reason']);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['magazine']);
        self::assertSame($magazine->getId(), $jsonData['magazine']['magazineId']);
        self::assertArrayKeysMatch(self::USER_SMALL_RESPONSE_KEYS, $jsonData['reported']);
        self::assertSame($reportedUser->getId(), $jsonData['reported']['userId']);
        self::assertArrayKeysMatch(self::USER_SMALL_RESPONSE_KEYS, $jsonData['reporting']);
        self::assertSame($user->getId(), $jsonData['reporting']['userId']);
        self::assertArrayKeysMatch(self::ENTRY_RESPONSE_KEYS, $jsonData['subject']);
        self::assertEquals($entry->getId(), $jsonData['subject']['entryId']);
        self::assertEquals('trashed', $jsonData['subject']['visibility']);
        self::assertEquals($entry->body, $jsonData['subject']['body']);
        self::assertEquals('approved', $jsonData['status']);
        self::assertSame(1, $jsonData['weight']);
        self::assertEqualsWithDelta($report->createdAt->getTimestamp(), \DateTimeImmutable::createFromFormat(\DateTimeImmutable::ATOM, $jsonData['createdAt'])->getTimestamp(), 5.0);
        self::assertEqualsWithDelta($consideredAt->getTimestamp(), \DateTimeImmutable::createFromFormat(\DateTimeImmutable::ATOM, $jsonData['consideredAt'])->getTimestamp(), 5.0);
        self::assertNotNull($jsonData['consideredBy']);
        self::assertArrayKeysMatch(self::USER_SMALL_RESPONSE_KEYS, $jsonData['consideredBy']);
        self::assertSame($user->getId(), $jsonData['consideredBy']['userId']);
    }

    public function testApiCanRejectReport(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();
        $magazine = $this->getMagazineByName('test');
        $reportedUser = $this->getUserByUsername('testuser');
        $entry = $this->getEntryByTitle('Report test', body: 'This is gonna be reported', magazine: $magazine, user: $reportedUser);

        $reportManager = $this->reportManager;
        $report = $reportManager->report(ReportDto::create($entry, 'I don\'t like it'), $user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine:reports:action');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('POST', "/api/moderate/magazine/{$magazine->getId()}/reports/{$report->getId()}/reject", server: ['HTTP_AUTHORIZATION' => $token]);
        $consideredAt = new \DateTimeImmutable();
        $adjustedConsideredAt = floor($consideredAt->getTimestamp() / 1000);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);
        $adjustedReceivedConsideredAt = floor(\DateTimeImmutable::createFromFormat(\DateTimeImmutable::ATOM, $jsonData['createdAt'])->getTimestamp() / 1000);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(MagazineRetrieveReportsApiTest::REPORT_RESPONSE_KEYS, $jsonData);
        self::assertEquals('entry_report', $jsonData['type']);
        self::assertEquals($report->reason, $jsonData['reason']);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['magazine']);
        self::assertSame($magazine->getId(), $jsonData['magazine']['magazineId']);
        self::assertArrayKeysMatch(self::USER_SMALL_RESPONSE_KEYS, $jsonData['reported']);
        self::assertSame($reportedUser->getId(), $jsonData['reported']['userId']);
        self::assertArrayKeysMatch(self::USER_SMALL_RESPONSE_KEYS, $jsonData['reporting']);
        self::assertSame($user->getId(), $jsonData['reporting']['userId']);
        self::assertArrayKeysMatch(self::ENTRY_RESPONSE_KEYS, $jsonData['subject']);
        self::assertEquals($entry->getId(), $jsonData['subject']['entryId']);
        self::assertEquals('visible', $jsonData['subject']['visibility']);
        self::assertEquals($entry->body, $jsonData['subject']['body']);
        self::assertEquals('rejected', $jsonData['status']);
        self::assertSame(1, $jsonData['weight']);
        self::assertEqualsWithDelta($report->createdAt->getTimestamp(), \DateTimeImmutable::createFromFormat(\DateTimeImmutable::ATOM, $jsonData['createdAt'])->getTimestamp(), 5.0);
        self::assertEqualsWithDelta($adjustedConsideredAt, $adjustedReceivedConsideredAt, 5.0);
        self::assertNotNull($jsonData['consideredBy']);
        self::assertArrayKeysMatch(self::USER_SMALL_RESPONSE_KEYS, $jsonData['consideredBy']);
        self::assertSame($user->getId(), $jsonData['consideredBy']['userId']);
    }
}
