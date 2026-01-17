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
                $mentionFromTag = $this->findMentionFromTag($match, $apTags);
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
                        $username = $actor instanceof User ? $actor->username : $actor->name;
                        $replace = $this->mentionManager->getUsername($username, true);
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

    private function findMentionFromTag(array $match, array $apTags): array
    {
        $res = [];
        foreach ($apTags as $tag) {
            if ('Mention' === $tag['type']) {
                if ($match[2] === $tag['href']) {
                    // the href in the tag array might be the same as the link from the text
                    $res[] = $tag;
                } elseif ($match[1] === $tag['name']) {
                    // or it might not be, but the linktext from the text might be the same as the name in the tag array
                    $res[] = $tag;
                } elseif (($host = parse_url($tag['href'], PHP_URL_HOST)) && "$match[1]@$host" === $tag['name']) {
                    // or the tag array might contain the full handle, but the linktext might only be the name part of the handle
                    $res[] = $tag;
                }
            }
        }

        return $res;
    }
}
