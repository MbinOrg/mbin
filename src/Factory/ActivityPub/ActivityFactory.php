<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Message;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Repository\TagLinkRepository;

class ActivityFactory
{
    public function __construct(
        private readonly TagLinkRepository $tagLinkRepository,
        private readonly EntryPageFactory $pageFactory,
        private readonly EntryCommentNoteFactory $entryNoteFactory,
        private readonly PostNoteFactory $postNoteFactory,
        private readonly PostCommentNoteFactory $postCommentNoteFactory,
        private readonly MessageFactory $messageFactory,
    ) {
    }

    public function create(ActivityPubActivityInterface $activity, bool $context = false): array
    {
        return match (true) {
            $activity instanceof Entry => $this->pageFactory->create($activity, $this->tagLinkRepository->getTagsOfContent($activity), $context),
            $activity instanceof EntryComment => $this->entryNoteFactory->create($activity, $this->tagLinkRepository->getTagsOfContent($activity), $context),
            $activity instanceof Post => $this->postNoteFactory->create($activity, $this->tagLinkRepository->getTagsOfContent($activity), $context),
            $activity instanceof PostComment => $this->postCommentNoteFactory->create($activity, $this->tagLinkRepository->getTagsOfContent($activity), $context),
            $activity instanceof Message => $this->messageFactory->build($activity, $context),
            default => throw new \LogicException(),
        };
    }
}
