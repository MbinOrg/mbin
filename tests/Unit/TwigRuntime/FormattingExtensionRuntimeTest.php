<?php

declare(strict_types=1);

namespace App\Tests\Unit\TwigRuntime;

use App\Markdown\MarkdownConverter;
use App\Tests\WebTestCase;
use App\Twig\Runtime\FormattingExtensionRuntime;

class FormattingExtensionRuntimeTest extends WebTestCase
{
    private FormattingExtensionRuntime $rt;

    public function setUp(): void
    {
        parent::setUp();

        $this->rt = new FormattingExtensionRuntime($this->createMock(MarkdownConverter::class));
    }

    public function testGetShortSentenceOnlyFirstParagraph()
    {
        $body = trim('
This is the first paragraph which is below the limit.

And a second paragraph.
        ');

        $actual = $this->rt->getShortSentence($body, length: 60);
        $expected = 'This is the first paragraph which is below the limit. ...';
        self::assertSame($expected, $actual);
    }

    public function testGetShortSentenceOnlyFirstParagraphLimited()
    {
        $body = trim('
This is the first paragraph which is over the limit.

And a second paragraph.
        ');

        $actual = $this->rt->getShortSentence($body, length: 10);
        $expected = 'This is ...';
        self::assertSame($expected, $actual);
    }

    public function testGetShortSentenceMultipleParagraphs()
    {
        $body = trim('
This is the first paragraph which is below the limit.

And a second paragraph. With more than one sentence. And so on, and so on.
        ');

        $actual = $this->rt->getShortSentence($body, length: 89, onlyFirstParagraph: false);
        $expected = "This is the first paragraph which is below the limit.\n\nAnd a second paragraph. ...";
        self::assertSame($expected, $actual);
    }

    public function testGetShortSentenceMultipleParagraphsPreLimit()
    {
        $body = trim('
This is the first paragraph which is below the limit.

And a second paragraph. With more than one sentence. And so on, and so on.
        ');

        $actual = $this->rt->getShortSentence($body, length: 90, onlyFirstParagraph: false);
        $expected = "This is the first paragraph which is below the limit.\n\nAnd a second paragraph. With more t...";
        self::assertSame($expected, $actual);
    }
}
