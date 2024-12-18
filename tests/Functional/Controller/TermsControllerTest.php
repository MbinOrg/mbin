<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\WebTestCase;

class TermsControllerTest extends WebTestCase
{
    public function testTermsPage(): void
    {
        $crawler = $this->client->request('GET', '/');
        $this->client->click($crawler->filter('.about.section a[href="/terms"]')->link());

        $this->assertSelectorTextContains('h1', 'Terms');
    }
}
