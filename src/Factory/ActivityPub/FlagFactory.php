<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Contracts\ReportInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\Report;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FlagFactory
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    #[ArrayShape([
        '@context' => 'mixed',
        'id' => 'string',
        'type' => 'string',
        'actor' => 'mixed',
        'to' => 'mixed',
        'object' => 'string',
        'audience' => 'string',
        'summary' => 'string',
        'content' => 'string',
    ])]
    public function build(Report $report, string $objectUrl): array
    {
        // mastodon does not accept a report that does not have an array as object.
        // I created an issue for it: https://github.com/mastodon/mastodon/issues/28159
        $mastodonObject = [
            $objectUrl,
            $report->reported->apPublicUrl ?? $this->urlGenerator->generate(
                'ap_user',
                ['username' => $report->reported->username],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ];

        // lemmy does not accept a report that does have an array as object.
        // I created an issue for it: https://github.com/LemmyNet/lemmy/issues/4217
        $lemmyObject = $objectUrl;

        if ('random' !== $report->magazine->name or $report->magazine->apId) {
            // apAttributedToUrl is not a standardized field,
            //  so it is not implemented by every software that supports groups.
            // Some don't have moderation at all, so it will probably remain optional in the future.
            $audience = $report->magazine->apPublicUrl ?? $this->urlGenerator->generate(
                'ap_magazine',
                ['name' => $report->magazine->name],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $object = $lemmyObject;
        } else {
            $audience = $report->reported->apPublicUrl ?? $this->urlGenerator->generate(
                'ap_user',
                ['username' => $report->reported->username],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $object = $mastodonObject;
        }

        $result = [
            '@context' => ActivityPubActivityInterface::CONTEXT_URL,
            'id' => $this->urlGenerator->generate(
                'ap_report',
                ['report_id' => $report->uuid],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'type' => 'Flag',
            'actor' => $report->reporting->apPublicUrl ?? $this->urlGenerator->generate(
                'ap_user',
                ['username' => $report->reporting->username],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'object' => $object,
            'audience' => $audience,
            'summary' => $report->reason,
            'content' => $report->reason,
        ];

        if ('random' !== $report->magazine->name or $report->magazine->apId) {
            $result['to'] = [
                $report->magazine->apPublicUrl ?? $this->urlGenerator->generate(
                    'ap_magazine',
                    ['name' => $report->magazine->name],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
            ];
        }

        return $result;
    }

    public function getPublicUrl(ReportInterface $subject): string
    {
        $publicUrl = $subject->getApId();
        if ($publicUrl) {
            return $publicUrl;
        }

        return match (str_replace('Proxies\\__CG__\\', '', \get_class($subject))) {
            Entry::class => $this->urlGenerator->generate('ap_entry', [
                'magazine_name' => $subject->magazine->name,
                'entry_id' => $subject->getId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
            EntryComment::class => $this->urlGenerator->generate('ap_entry_comment', [
                'magazine_name' => $subject->magazine->name,
                'entry_id' => $subject->entry->getId(),
                'comment_id' => $subject->getId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
            Post::class => $this->urlGenerator->generate('ap_post', [
                'magazine_name' => $subject->magazine->name,
                'post_id' => $subject->getId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
            PostComment::class => $this->urlGenerator->generate('ap_post_comment', [
                'magazine_name' => $subject->magazine->name,
                'post_id' => $subject->post->getId(),
                'comment_id' => $subject->getId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
            default => throw new \LogicException("can't handle ".\get_class($subject)),
        };
    }
}
