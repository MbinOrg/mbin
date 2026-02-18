<?php

declare(strict_types=1);

namespace App\Tests\Unit\TwigRuntime;

use App\Markdown\MarkdownConverter;
use App\Tests\WebTestCase;
use App\Twig\Runtime\FormattingExtensionRuntime;
use PHPUnit\Framework\Attributes\DataProvider;

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

    #[DataProvider('provideShortenNumberData')]
    public function testShortenNumber(int $number, string $expected): void
    {
        self::assertEquals($expected, $this->rt->abbreviateNumber($number));
    }

    public static function provideShortenNumberData(): array
    {
        return [
            [
                'number' => 0,
                'expected' => '0',
            ],
            [
                'number' => 1234,
                'expected' => '1.23K',
            ],
            [
                'number' => 123456789,
                'expected' => '123.46M',
            ],
            [
                'number' => 1999,
                'expected' => '2K',
            ],
            [
                'number' => 1994,
                'expected' => '1.99K',
            ],
            [
                'number' => 3548,
                'expected' => '3.55K',
            ],
            [
                'number' => 1234567890,
                'expected' => '1.23B',
            ],
            [
                'number' => 12345678900000,
                'expected' => '12345.68B',
            ],
        ];
    }
}
