<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Entry;

use App\Tests\WebTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class EntryDeleteControllerTest extends WebTestCase
{
    public function testUserCanDeleteEntry()
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByName('acme');
        $entry = $this->getEntryByTitle('deletion test', body: 'will be deleted', magazine: $magazine, user: $user);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/m/acme');

        $this->assertSelectorExists('form[action$="delete"]');
        $this->client->submit(
            $crawler->filter('form[action$="delete"]')->selectButton('Delete')->form()
        );

        $this->assertResponseRedirects();
    }

    public function testUserCanSoftDeleteEntry()
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByName('acme');
        $entry = $this->getEntryByTitle('deletion test', body: 'will be deleted', magazine: $magazine, user: $user);
        $comment = $this->createEntryComment('only softly', $entry, $user);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/m/acme');

        $this->assertSelectorExists('form[action$="delete"]');
        $this->client->submit(
            $crawler->filter('form[action$="delete"]')->selectButton('Delete')->form()
        );

        $this->assertResponseRedirects();
        $this->client->request('GET', "/m/acme/t/{$entry->getId()}/deletion-test");

        $translator = $this->getService(TranslatorInterface::class);

        $this->assertSelectorTextContains("#entry-{$entry->getId()} header", $translator->trans('deleted_by_author'));
    }
}
