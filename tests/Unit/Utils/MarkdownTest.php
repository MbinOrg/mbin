<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils;

use App\Markdown\MarkdownConverter;
use App\Markdown\RenderTarget;
use App\Tests\WebTestCase;

class MarkdownTest extends WebTestCase
{
    public function testMagazineLinks(): void
    {
        $text = 'This should belong to !magazine@kbin.test2';
        $markdown = $this->markdownConverter->convertToHtml($text, [MarkdownConverter::RENDER_TARGET => RenderTarget::Page]);
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
        $markdown = $this->markdownConverter->convertToHtml($text, [MarkdownConverter::RENDER_TARGET => RenderTarget::Page]);
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
        $markdown = $this->markdownConverter->convertToHtml($text, [MarkdownConverter::RENDER_TARGET => RenderTarget::Page]);
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
        $markdown = $this->markdownConverter->convertToHtml($text, [MarkdownConverter::RENDER_TARGET => RenderTarget::Page]);
        self::assertStringContainsString('https://kbin.test2', $markdown);
    }

    public function testMentionLink(): void
    {
        $text = 'Hi @admin@kbin.test2';
        $markdown = $this->markdownConverter->convertToHtml($text, [MarkdownConverter::RENDER_TARGET => RenderTarget::Page]);
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
        $markdown = $this->markdownConverter->convertToHtml($text, [MarkdownConverter::RENDER_TARGET => RenderTarget::Page]);
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
        $markdown = $this->markdownConverter->convertToHtml($text, [MarkdownConverter::RENDER_TARGET => RenderTarget::Page]);
        // assert that this community does not exist, and we get a search link for it that does not link to the external instance
        self::assertStringContainsString('https://kbin.test2', $markdown);
    }
}
