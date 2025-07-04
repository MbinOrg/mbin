<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Markdown\MarkdownConverter;
use Twig\Extension\RuntimeExtensionInterface;

class FormattingExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(private readonly MarkdownConverter $markdownConverter)
    {
    }

    public function convertToHtml(?string $value, string $sourceType = ''): string
    {
        return $value ? $this->markdownConverter->convertToHtml($value, $sourceType) : '';
    }

    public function getShortSentence(?string $val, $length = 330, $striptags = false): string
    {
        if (!$val) {
            return '';
        }
        $body = $striptags ? strip_tags(html_entity_decode($val)) : $val;
        $body = wordwrap(trim($body), $length);
        $body = explode("\n", $body);

        return trim($body[0]).(isset($body[1]) ? '...' : '');
    }
}
