<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Magazine\Panel;

use App\DTO\ReportDto;
use App\Tests\WebTestCase;

class MagazineReportControllerTest extends WebTestCase
{
    public function testModCanSeeEntryReports(): void
    {
        $this->client->loginUser($user = $this->getUserByUsername('JohnDoe'));
        $user2 = $this->getUserByUsername('JaneDoe');

        $entryComment = $this->createEntryComment('Test comment 1');
        $postComment = $this->createPostComment('Test post 1');

        foreach ([$entryComment, $postComment, $entryComment->entry, $postComment->post] as $subject) {
            $this->reportManager->report(
                ReportDto::create($subject, 'test reason'),
                $user
            );
        }

        $this->client->request('GET', '/');
        $crawler = $this->client->request('GET', '/m/acme/panel/reports');

        $this->assertSelectorTextContains('#main .options__main a.active', 'Reports');
        $this->assertEquals(
            4,
            $crawler->filter('#main .report')->count()
        );
    }

    public function testUnauthorizedUserCannotSeeReports(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $this->getMagazineByName('acme');

        $this->client->request('GET', '/m/acme/panel/reports');

        $this->assertResponseStatusCodeSame(403);
    }
}
