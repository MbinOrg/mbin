<?php

declare(strict_types=1);

namespace App\Service\ActivityPub;

use App\Service\ActivityPubManager;

class ApObjectExtractor
{
    public const MARKDOWN_TYPE = 'text/markdown';

    public function __construct(
        private readonly MarkdownConverter $markdownConverter,
        private readonly ActivityPubManager $activityPubManager,
    ) {
    }

    public function getMarkdownBody(array $object): ?string
    {
        $content = $object['content'] ?? null;
        $source = $object['source'] ?? null;

        // object has no content nor source to extract body from
        if (empty($content) && empty($source)) {
            return null;
        }

        if ($source && (isset($source['mediaType']) && self::MARKDOWN_TYPE === $source['mediaType'])) {
            // markdown source found, return them
            return $source['content'] ?? null;
        } elseif ($content && (isset($object['mediaType']) && self::MARKDOWN_TYPE === $object['mediaType'])) {
            // markdown source isn't found but object's content is specified
            // to be markdown, also return them
            return $content;
        } else {
            // assuming default content mediaType of text/html,
            // returning html -> markdown conversion of content
            return $this->markdownConverter->convert($content);
        }
    }

    public function getExternalMediaBody(array $object): ?string
    {
        $body = null;

        if (isset($object['attachment'])) {
            $attachments = $object['attachment'];

            if ($images = $this->activityPubManager->handleExternalImages($attachments)) {
                $body .= "\n\n".implode(
                    "  \n",
                    array_map(fn ($image) => "![{$image->name}]({$image->url})", $images)
                );
            }

            if ($videos = $this->activityPubManager->handleExternalVideos($attachments)) {
                $body .= "\n\n".implode(
                    "  \n",
                    array_map(fn ($video) => "![{$video->name}]({$video->url})", $videos)
                );
            }
        }

        return $body;
    }
}
