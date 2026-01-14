<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Markdown\MarkdownConverter;
use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use Symfony\Component\Uid\Uuid;
use Twig\Extension\RuntimeExtensionInterface;

class FormattingExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly MarkdownConverter $markdownConverter,
        // private readonly SqlFormatter $sqlFormatter,
    ) {
    }

    public function convertToHtml(?string $value, string $sourceType = ''): string
    {
        return $value ? $this->markdownConverter->convertToHtml($value, $sourceType) : '';
    }

    public function getShortSentence(?string $val, $length = 330, $striptags = false, bool $onlyFirstParagraph = true): string
    {
        if (!$val) {
            return '';
        }

        $body = $striptags ? strip_tags(html_entity_decode($val)) : $val;

        if ($onlyFirstParagraph) {
            $body = wordwrap(trim($body), $length);
            $lines = explode("\n", $body);

            $shortened = trim($lines[0]);
            $ellipsis = isset($lines[1]) ? ' ...' : '';
        } elseif (\strlen($body) <= $length) {
            $shortened = $body;
            $ellipsis = '';
        } else {
            $sentenceTolerance = 12;
            $limit = $length - 1;
            $sentenceDelimiters = ['. ', ', ', '; ', "\n", "\t", "\f", "\v"];
            $sentencePreLimit = self::strrposMulti($body, $sentenceDelimiters, $limit);
            if ($sentencePreLimit > -1 && $sentencePreLimit >= $length - $sentenceTolerance) {
                $limit = $sentencePreLimit;
                $ellipsis = ' ...';
            } else {
                $ellipsis = '...';
            }

            $shortened = trim(substr($body, 0, $limit + 1));
        }

        return $shortened.$ellipsis;
    }

    private static function strrposMulti(string $haystack, array $needle, int $offset): int
    {
        $offset = $offset - \strlen($haystack);
        $pos = -1;
        foreach ($needle as $n) {
            $idx = strrpos($haystack, $n, $offset);
            if (false !== $idx) {
                $pos = max($pos, $idx);
            }
        }

        return $pos;
    }

    public function abbreviateNumber(int|float $value): string
    {
        // this implementation is offly simple, but therefore fast
        if ($value < 1000) {
            return ''.$value;
        } elseif ($value < 1000000) {
            return round($value / 1000, 2).'K';
        } elseif ($value < 1000000000) {
            return round($value / 1000000, 2).'M';
        } else {
            return round($value / 1000000000, 2).'B';
        }
    }

    public function uuidEnd(?Uuid $uuid): string
    {
        $string = $uuid->toString();
        $parts = explode('-', $string);

        return end($parts);
    }

    public function formatQuery(string $query): string
    {
        $formatter = new SqlFormatter(new NullHighlighter());

        return $formatter->format($query);
    }
}
