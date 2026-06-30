<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Contracts\HashtagableInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Message;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Factory\Contract\ActivityFactoryInterface;
use App\Repository\TagLinkRepository;
use App\Service\SwitchingServiceRegistry;

class ActivityFactory
{
    public function __construct(
        private readonly TagLinkRepository $tagLinkRepository,
        private readonly SwitchingServiceRegistry $serviceRegistry,
    ) {
    }

    public function create(ActivityPubActivityInterface $activity, bool $context = false): array
    {
        $hashtags = $activity instanceof HashtagableInterface ? $this->tagLinkRepository->getTagsOfContent($activity) : [];
        $factory = $this->serviceRegistry->getService($activity, ActivityFactoryInterface::class);
        return $factory->create($activity, $hashtags, $context);
    }
}
