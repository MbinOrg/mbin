<?php

declare(strict_types=1);

namespace App\Service\ActivityPub;

use App\Entity\Magazine;
use App\Entity\User;
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
        private readonly ActivityPubManager $activityPubManager,
    ) {
    }

    public function convert(string $value, array $apTags): string
    {
        $converter = new HtmlConverter(['strip_tags' => true]);
        $converter->getEnvironment()->addConverter(new TableConverter());
        $converter->getEnvironment()->addConverter(new StrikethroughConverter());
        $value = stripslashes($converter->convert($value));

        // an example value: [@user](https://some.instance.tld/u/user)
        preg_match_all('/\[([^]]*)\] *\(([^)]*)\)/i', $value, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if ($this->mentionManager->extract($match[1])) {
                $mentionFromTag = array_filter($apTags, fn ($tag) => 'Mention' === ($tag['type'] ?? '') && $match[2] === ($tag['href'] ?? ''));
                if (\count($mentionFromTag)) {
                    $mentionFromTagObj = $mentionFromTag[array_key_first($mentionFromTag)];
                    $mentioned = null;
                    try {
                        $mentioned = $this->activityPubManager->findActorOrCreate($mentionFromTagObj['href']);
                    } catch (\Throwable) {
                    }
                    if ($mentioned instanceof User) {
                        $replace = $this->mentionManager->getUsername($mentioned->username, true);
                    } elseif ($mentioned instanceof Magazine) {
                        $replace = $this->mentionManager->getUsername('@'.$mentioned->name, true);
                    } else {
                        $replace = $mentionFromTagObj['name'] ?? $match[1];
                    }
                } else {
                    try {
                        $actor = $this->activityPubManager->findActorOrCreate($match[2]);
                        $replace = '@'.($actor instanceof User ? $actor->username : $actor->name);
                    } catch (\Throwable) {
                        $replace = $match[1];
                    }
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
