<?php

namespace App\Factory\Entry;

use App\Entity\EntryComment;
use App\Factory\Contract\ContentUrlFactory;
use App\Service\Contracts\SwitchableService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @implements SwitchableService
 * @implements ContentUrlFactory<EntryComment>
 */
readonly class EntryCommentUrlFactory implements SwitchableService, ContentUrlFactory
{

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ){}

    public function getSupportedTypes(): array
    {
        return [EntryComment::class];
    }

    function getActivityPubId($subject): string
    {
        if ($subject->apId) {
            return $subject->apId;
        }

        return $this->urlGenerator->generate('ap_entry_comment', [
            'magazine_name' => $subject->magazine->name,
            'entry_id' => $subject->entry->getId(),
            'comment_id' => $subject->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    function getLocalUrl($subject): string
    {
        return $this->urlGenerator->generate('entry_comment_view', [
            'magazine_name' => $subject->magazine->name,
            'entry_id' => $subject->entry->getId(),
            'slug' => $subject->entry->slug ?? '-',
            'comment_id' => $subject->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
