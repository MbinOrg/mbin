<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils;

use App\Entity\Magazine;
use App\Markdown\MarkdownConverter;
use App\Markdown\RenderTarget;
use App\Tests\WebTestCase;

use function PHPUnit\Framework\assertStringContainsString;

class MarkdownTest extends WebTestCase
{
    public function testMagazineLinks(): void
    {
        $text = 'This should belong to !magazine@kbin.test2';
        $markdown = $this->markdownConverter->convertToHtml($text, context: [MarkdownConverter::RENDER_TARGET => RenderTarget::Page]);
        // assert that this community does not exist, and we get a search link for it that does not link to the external instance
        self::assertStringContainsString('search', $markdown);
        self::assertStringNotContainsString('(', $markdown);
        self::assertStringNotContainsString(')', $markdown);
        self::assertStringNotContainsString('[', $markdown);
        self::assertStringNotContainsString(']', $markdown);
        self::assertStringNotContainsString('https://kbin.test2', $markdown);
    }

    public function testMagazineLinks2(): void
    {
        $text = 'Lots of activity on [!fedibridge@lemmy.dbzer0.com](https://lemmy.dbzer0.com/c/fedibridge) following Reddit paywall announcements';
        $markdown = $this->markdownConverter->convertToHtml($text, context: [MarkdownConverter::RENDER_TARGET => RenderTarget::Page]);
        // assert that this community does not exist, and we get a search link for it that does not link to the external instance
        self::assertStringContainsString('search', $markdown);
        self::assertStringNotContainsString('(', $markdown);
        self::assertStringNotContainsString(')', $markdown);
        self::assertStringNotContainsString('[', $markdown);
        self::assertStringNotContainsString(']', $markdown);
        self::assertStringNotContainsString('https://lemmy.dbzer0.com', $markdown);
    }

    public function testLemmyMagazineLinks(): void
    {
        $text = 'This should belong to [!magazine@kbin.test2](https://kbin.test2/m/magazine)';
        $markdown = $this->markdownConverter->convertToHtml($text, context: [MarkdownConverter::RENDER_TARGET => RenderTarget::Page]);
        // assert that this community does not exist, and we get a search link for it that does not link to the external instance
        self::assertStringContainsString('search', $markdown);
        self::assertStringNotContainsString('(', $markdown);
        self::assertStringNotContainsString(')', $markdown);
        self::assertStringNotContainsString('[', $markdown);
        self::assertStringNotContainsString(']', $markdown);
        self::assertStringNotContainsString('https://kbin.test2', $markdown);
    }

    public function testExternalMagazineLinks(): void
    {
        $text = 'This should belong to [this magazine](https://kbin.test2/m/magazine)';
        $markdown = $this->markdownConverter->convertToHtml($text, context: [MarkdownConverter::RENDER_TARGET => RenderTarget::Page]);
        self::assertStringContainsString('https://kbin.test2', $markdown);
    }

    public function testMentionLink(): void
    {
        $text = 'Hi @admin@kbin.test2';
        $markdown = $this->markdownConverter->convertToHtml($text, context: [MarkdownConverter::RENDER_TARGET => RenderTarget::Page]);
        // assert that this community does not exist, and we get a search link for it that does not link to the external instance
        self::assertStringContainsString('search', $markdown);
        self::assertStringNotContainsString('(', $markdown);
        self::assertStringNotContainsString(')', $markdown);
        self::assertStringNotContainsString('[', $markdown);
        self::assertStringNotContainsString(']', $markdown);
        self::assertStringNotContainsString('https://kbin.test2', $markdown);
    }

    public function testNestedMentionLink(): void
    {
        $text = 'Hi [@admin@kbin.test2](https://kbin.test2/u/admin)';
        $markdown = $this->markdownConverter->convertToHtml($text, context: [MarkdownConverter::RENDER_TARGET => RenderTarget::Page]);
        // assert that this community does not exist, and we get a search link for it that does not link to the external instance
        self::assertStringContainsString('search', $markdown);
        self::assertStringNotContainsString('(', $markdown);
        self::assertStringNotContainsString(')', $markdown);
        self::assertStringNotContainsString('[', $markdown);
        self::assertStringNotContainsString(']', $markdown);
        self::assertStringNotContainsString('https://kbin.test2', $markdown);
    }

    public function testExternalMentionLink(): void
    {
        $text = 'You should really talk to your [instance admin](https://kbin.test2/u/admin)';
        $markdown = $this->markdownConverter->convertToHtml($text, context: [MarkdownConverter::RENDER_TARGET => RenderTarget::Page]);
        // assert that this community does not exist, and we get a search link for it that does not link to the external instance
        self::assertStringContainsString('https://kbin.test2', $markdown);
    }

    public function testExternalMagazineLocalEntryLink(): void
    {
        $m = new Magazine('test@kbin.test2', 'test', null, null, null, false, false, null);
        $m->apId = 'test@kbin.test2';
        $m->apInboxUrl = 'https://kbin.test2/inbox';
        $m->apPublicUrl = 'https://kbin.test2/m/test';
        $m->apProfileId = 'https://kbin.test2/m/test';
        $this->entityManager->persist($m);
        $entry = $this->getEntryByTitle('test', magazine: $m);
        $this->entityManager->flush();
        $text = "Look at my post at https://kbin.test/m/test@kbin.test2/t/{$entry->getId()}/some-slug";
        $markdown = $this->markdownConverter->convertToHtml($text, context: [MarkdownConverter::RENDER_TARGET => RenderTarget::Page]);
        assertStringContainsString('entry-inline', $markdown);
    }

    public function testExternalMagazineLocalPostLink(): void
    {
        $m = new Magazine('test@kbin.test2', 'test', null, null, null, false, false, null);
        $m->apId = 'test@kbin.test2';
        $m->apInboxUrl = 'https://kbin.test2/inbox';
        $m->apPublicUrl = 'https://kbin.test2/m/test';
        $m->apProfileId = 'https://kbin.test2/m/test';
        $this->entityManager->persist($m);
        $post = $this->createPost('test', magazine: $m);
        $this->entityManager->flush();
        $text = "Look at my post at https://kbin.test/m/test@kbin.test2/p/{$post->getId()}/some-slug";
        $markdown = $this->markdownConverter->convertToHtml($text, context: [MarkdownConverter::RENDER_TARGET => RenderTarget::Page]);
        assertStringContainsString('post-inline', $markdown);
    }

    public function testLocalNotMatchingUrl(): void
    {
        $m = new Magazine('test@kbin.test2', 'test', null, null, null, false, false, null);
        $m->apId = 'test@kbin.test2';
        $m->apInboxUrl = 'https://kbin.test2/inbox';
        $m->apPublicUrl = 'https://kbin.test2/m/test';
        $m->apProfileId = 'https://kbin.test2/m/test';
        $this->entityManager->persist($m);
        $entry = $this->getEntryByTitle('test', magazine: $m);
        $this->entityManager->flush();
        $text = "Look at my post at https://kbin.test/m/test@kbin.test2/t/{$entry->getId()}/some-slug/votes";
        $markdown = $this->markdownConverter->convertToHtml($text, context: [MarkdownConverter::RENDER_TARGET => RenderTarget::Page]);
        assertStringContainsString("https://kbin.test/m/test@kbin.test2/t/{$entry->getId()}/some-slug/votes", $markdown);
    }

    public function testBracketsInLinkTitle(): void
    {
        $m = new Magazine('test@kbin.test2', 'test', null, null, null, false, false, null);
        $m->apId = 'test@kbin.test2';
        $m->apInboxUrl = 'https://kbin.test2/inbox';
        $m->apPublicUrl = 'https://kbin.test2/m/test';
        $m->apProfileId = 'https://kbin.test2/m/test';
        $this->entityManager->persist($m);
        $entry = $this->getEntryByTitle('test', magazine: $m);
        $this->entityManager->flush();
        $text = "[Look at my post (or not, your choice)](https://kbin.test/m/test@kbin.test2/t/{$entry->getId()}/some-slug/favourites)";
        $markdown = $this->markdownConverter->convertToHtml($text, context: [MarkdownConverter::RENDER_TARGET => RenderTarget::Page]);
        assertStringContainsString("https://kbin.test/m/test@kbin.test2/t/{$entry->getId()}/some-slug/favourites", $markdown);
    }
}
