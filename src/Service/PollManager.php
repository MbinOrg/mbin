<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\ContentWithPollDto;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Poll;
use App\Entity\PollChoice;
use App\Entity\PollVote;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Event\Poll\PollEditedEvent;
use App\Event\Poll\PollPreEditedEvent;
use App\Event\Poll\PollVoteEvent;
use App\Exception\PollHasEndedException;
use App\Repository\EntryCommentRepository;
use App\Repository\EntryRepository;
use App\Repository\PostCommentRepository;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

readonly class PollManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
        private EntryRepository $entryRepository,
        private EntryCommentRepository $entryCommentRepository,
        private PostRepository $postRepository,
        private PostCommentRepository $postCommentRepository,
    ) {
    }

    public function createPoll(ContentWithPollDto $dto, Entry|EntryComment|Post|PostComment $object): Poll
    {
        $poll = new Poll();
        $poll->multipleChoice = $dto->isMultipleChoicePoll;
        $this->entityManager->persist($poll);

        $this->createChoices($dto, $poll);
        $object->poll = $poll;
        $this->entityManager->flush();

        return $poll;
    }

    public function edit(?Poll $poll, ContentWithPollDto $dto, User $editor): void
    {
        $this->eventDispatcher->dispatch(new PollPreEditedEvent($poll, $this->getContentOfPoll($poll), $editor));
        $poll->endDate = $dto->pollEndsAt;
        $poll->multipleChoice = $dto->isMultipleChoicePoll;
        foreach ($poll->choices as $choice) {
            // remove all choices, which also removes all votes
            $this->entityManager->remove($choice);
        }
        $this->createChoices($dto, $poll);
        $this->entityManager->flush();
        $this->eventDispatcher->dispatch(new PollEditedEvent($poll, $this->getContentOfPoll($poll), $editor));
    }

    private function createChoices(ContentWithPollDto $dto, Poll $poll): void
    {
        foreach ($dto->choices as $choice) {
            if (!trim($choice ?? '')) {
                continue;
            }
            $pollChoice = new PollChoice();
            $pollChoice->poll = $poll;
            $pollChoice->name = trim($choice);
            $poll->choices[] = $pollChoice;
            $this->entityManager->persist($pollChoice);
        }
    }

    /**
     * @param Entry|EntryComment|Post|PostComment $content              where the poll belongs to
     * @param string[]                            $choices              the choices a user votes for
     * @param bool                                $allowMultipleChoices if the context allows for the user to vote again, that should only be allowed from activity pub
     *
     * @throws ORMException
     * @throws PollHasEndedException
     * @throws \LogicException
     */
    public function vote(Poll $poll, Entry|EntryComment|Post|PostComment $content, User $user, array $choices, bool $allowMultipleChoices = false): void
    {
        if (!$poll->multipleChoice && \sizeof($choices) > 1) {
            throw new \LogicException('Poll does not allow multiple choices.');
        }
        if (0 === \sizeof($choices)) {
            throw new \LogicException('No choice found');
        }
        if ($poll->hasUserVoted($user) && !$allowMultipleChoices) {
            throw new \LogicException('Already voted');
        }
        if ($poll->hasEnded()) {
            throw new PollHasEndedException('Poll has already ended');
        }

        $voteEntities = [];
        foreach (array_unique($choices) as $choice) {
            $choiceEntity = $poll->findChoice($choice);
            $vote = new PollVote();
            $vote->voter = $user;
            $vote->poll = $poll;
            $vote->choice = $choiceEntity;
            $this->entityManager->persist($vote);
            $voteEntities[] = $vote;
            $this->entityManager->flush();
            $this->entityManager->refresh($choiceEntity);
            $choiceEntity->updateVoteCount();
        }
        $this->entityManager->flush();
        $this->entityManager->refresh($poll);
        $poll->updateVoterCount();
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new PollVoteEvent($poll, $content, $user, $voteEntities));
    }

    public function getContentOfPoll(Poll $poll): Entry|EntryComment|Post|PostComment|null
    {
        return $this->entryRepository->findOneBy(['poll' => $poll])
            ?? $this->entryCommentRepository->findOneBy(['poll' => $poll])
            ?? $this->postRepository->findOneBy(['poll' => $poll])
            ?? $this->postCommentRepository->findOneBy(['poll' => $poll])
        ;
    }

    public function hasPollProperties(array $object): bool
    {
        if ('Question' === $object['type'] && isset($object['votersCount']) && isset($object['endTime']) && (isset($object['anyOf']) || isset($object['oneOf']))) {
            $choices = $object['anyOf'] ?? $object['oneOf'];
            if (\is_array($choices)) {
                foreach ($choices as $choice) {
                    if (!\is_array($choice)) {
                        return false;
                    }
                    if (isset($choice['type']) && 'Note' === $choice['type'] && isset($choice['name']) && \is_string($choice['name'])) {
                        if (isset($choice['replies']['type']) && 'Collection' === $choice['replies']['type'] && isset($choice['replies']['totalItems'])) {
                            // this is the positive case
                        } else {
                            return false;
                        }
                    } else {
                        return false;
                    }
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Call PollManager::canCreateFromApObject() first, this that $object contains all the necessary information.
     *
     * @throws \DateMalformedStringException
     */
    public function createFromApObject(array $object): Poll
    {
        $poll = new Poll();
        $poll->endDate = new \DateTimeImmutable($object['endTime']);
        $poll->createdAt = new \DateTimeImmutable($object['published']);
        $poll->isRemote = true;
        $poll->voterCount = $object['votersCount'];
        $poll->multipleChoice = isset($object['anyOf']);

        $choices = $object['anyOf'] ?? $object['oneOf'];
        $choiceNames = [];
        foreach ($choices as $choice) {
            if (array_find($choiceNames, fn (string $choiceName) => $choiceName === $choice['name'])) {
                // do not create duplicate choices
                continue;
            }
            $pollChoice = new PollChoice();
            $pollChoice->poll = $poll;
            $pollChoice->name = $choice['name'];
            $pollChoice->voteCount = $choice['replies']['totalItems'];
            $this->entityManager->persist($pollChoice);
        }
        $this->entityManager->persist($poll);
        $this->entityManager->flush();

        return $poll;
    }

    /**
     * Call PollManager::canCreateFromApObject() first, this that $payload contains all the necessary information.
     */
    public function updatePollCounts(Poll $poll, array $payload): void
    {
        $poll->voterCount = $payload['voterCount'];

        $choices = $payload['anyOf'] ?? $payload['oneOf'];
        foreach ($choices as $choice) {
            $pollChoice = $poll->findChoice($choice['name']);
            $pollChoice->voteCount = $choice['replies']['totalItems'];
        }
        $this->entityManager->flush();
    }
}
