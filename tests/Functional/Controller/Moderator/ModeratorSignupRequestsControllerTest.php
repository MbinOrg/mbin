<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Moderator;

use App\Tests\WebTestCase;

class ModeratorSignupRequestsControllerTest extends WebTestCase
{
    public function testModeratorCanViewSignupRequests(): void
    {
        $this->settingsManager->set('MBIN_NEW_USERS_NEED_APPROVAL', true);

        $this->client->loginUser($this->getUserByUsername('moderator', isModerator: true));

        $crawler = $this->client->request('GET', '/');
        $this->client->click($crawler->filter('#header menu')->selectLink('Signup requests')->link());

        $this->assertSelectorTextContains('#main h3', 'Signup Requests');
    }
}
