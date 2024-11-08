<?php

declare(strict_types=1);

namespace App\Service\ActivityPub;

use App\Service\ActivityPubManager;
use App\Service\MentionManager;
use App\Service\TagExtractor;
use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\HtmlConverter;

class MarkdownConverter
{
    public function __construct(
        private readonly TagExtractor $tagExtractor,
        private readonly MentionManager $mentionManager,
        private readonly ActivityPubManager $activityPubManager
    ) {
    }

    public function convert(string $value): string
    {
        $converter = new HtmlConverter(['strip_tags' => true]);
        $converter->getEnvironment()->addConverter(new TableConverter());
        $value = stripslashes($converter->convert($value));

        preg_match_all('/\[([^]]*)\] *\(([^)]*)\)/i', $value, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if ($this->mentionManager->extract($match[1])) {
                try {
                    $replace = '@'.$this->activityPubManager->webfinger($match[2])->getHandle();
                } catch (\Exception $e) {
                    $replace = $match[1];
                }
                $value = str_replace($match[0], $replace, $value);
            }

            if ($this->tagExtractor->extract($match[1])) {
                $value = str_replace($match[0], $match[1], $value);
            }
        }

        return $value;
    }
}
