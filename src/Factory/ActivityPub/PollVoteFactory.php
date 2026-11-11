<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\PollVote;
use App\Repository\ApActivityRepository;
use App\Service\ActivityPub\ContextsProvider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PollVoteFactory
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ContextsProvider $contextsProvider,
        private readonly PersonFactory $personFactory,
        private readonly ApActivityRepository $apActivityRepository,
    ) {
    }

    public function build(PollVote $vote, bool $includeContext = true): array
    {
        $actorUrl = $this->personFactory->getActivityPubId($vote->getUser());
        $content = $vote->poll->getSubject() ?? throw new \LogicException('Could not find the subject of the vote');

        $result = [
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_poll_vote', ['username' => $vote->getUser()->username, 'uuid' => $vote->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'attributedTo' => $actorUrl,
            'to' => [$this->personFactory->getActivityPubId($content->user)],
            'cc' => [],
            'type' => 'Note',
            'published' => $vote->createdAt->format(DATE_ATOM),
            'inReplyTo' => $content->apId ?? $this->apActivityRepository->getGlobalUrlOfEntity($content),
            'name' => $vote->choice->name,
        ];

        if (!$includeContext) {
            unset($result['@context']);
        }

        return $result;
    }
}
