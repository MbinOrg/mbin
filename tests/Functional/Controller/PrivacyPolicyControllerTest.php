<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\WebTestCase;

class PrivacyPolicyControllerTest extends WebTestCase
{
    public function testPrivacyPolicyPage(): void
    {
        $crawler = $this->client->request('GET', '/');
        $this->client->click($crawler->filter('.about.section a[href="/privacy-policy"]')->link());

        $this->assertSelectorTextContains('h1', 'Privacy policy');
    }
}
