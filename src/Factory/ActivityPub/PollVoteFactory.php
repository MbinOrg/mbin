<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\PollVote;
use App\Repository\ApActivityRepository;
use App\Repository\EntryRepository;
use App\Repository\PostRepository;
use App\Service\ActivityPub\ContextsProvider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PollVoteFactory
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ContextsProvider $contextsProvider,
        private readonly EntryRepository $entryRepository,
        private readonly PostRepository $postRepository,
        private readonly PersonFactory $personFactory,
        private readonly ApActivityRepository $apActivityRepository,
    ) {
    }

    public function build(PollVote $vote, bool $includeContext = true): array
    {
        $actorUrl = $this->personFactory->getActivityPubId($vote->getUser());
        $content = $this->entryRepository->findOneBy(['poll' => $vote->poll]) ?? $this->postRepository->findOneBy(['poll' => $vote->poll]) ?? throw new \LogicException();

        $result = [
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_poll_vote', ['username' => $vote->getUser()->username, 'uuid' => $vote->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'attributedTo' => $actorUrl,
            'to' => [$this->personFactory->getActivityPubId($content->user)],
            'cc' => [],
            'type' => 'Note',
            'published' => $vote->createdAt->format(DATE_ATOM),
            'inReplyTo' => $content->apId ?? $this->apActivityRepository->getLocalUrlOfEntity($content, true),
            'name' => $vote->choice->name,
        ];

        if (!$includeContext) {
            unset($result['@context']);
        }

        return $result;
    }
}
