<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Tests\WebTestCase;

class PollManagerTest extends WebTestCase
{
    public function testCreateFromApObjectTracksChoiceNames(): void
    {
        $endTime = new \DateTimeImmutable('+7 days');
        $apObject = $this->createAPPollObject('Option A', $endTime, 5, isMultipleChoice: true);
        $apObject['anyOf'][] = $this->createAPPollChoiceObject('Option B', 3);
        $apObject['anyOf'][] = $this->createAPPollChoiceObject('Option C', 2);

        $poll = $this->pollManager->createFromApObject($apObject);

        self::assertTrue($poll->isRemote);
        self::assertEquals(5, $poll->voterCount);

        $choiceNames = $poll->choices->map(fn ($c) => $c->name)->toArray();
        self::assertEquals(['Option A', 'Option B', 'Option C'], $choiceNames);

        $voteCounts = $poll->choices->map(fn ($c) => $c->voteCount)->toArray();
        self::assertEquals([5, 3, 2], $voteCounts);
    }

    public function testCreateFromApObjectDoesNotCreateDuplicateChoices(): void
    {
        $endTime = new \DateTimeImmutable('+7 days');
        $apObject = $this->createAPPollObject('Option A', $endTime, 3, isMultipleChoice: true);
        $apObject['anyOf'][] = $this->createAPPollChoiceObject('Option A', 3);
        $apObject['anyOf'][] = $this->createAPPollChoiceObject('Option B', 2);

        $poll = $this->pollManager->createFromApObject($apObject);

        self::assertCount(2, $poll->choices);
    }

    public function testCreateFromApObjectSetsMultipleChoiceCorrectly(): void
    {
        $endTime = new \DateTimeImmutable('+7 days');
        $APObjectOneOf = $this->createAPPollObject('Single Choice', $endTime, 10);

        $poll = $this->pollManager->createFromApObject($APObjectOneOf);
        self::assertFalse($poll->multipleChoice);

        $APObjectAnyOf = $this->createAPPollObject('Choice 1', $endTime, 10, isMultipleChoice: true);
        $APObjectAnyOf['anyOf'][] = $this->createAPPollChoiceObject('Choice 2', 5);

        $poll = $this->pollManager->createFromApObject($APObjectAnyOf);
        self::assertTrue($poll->multipleChoice);
    }

    public function testCreateFromApObjectRefreshesPollAfterCreation(): void
    {
        $endTime = new \DateTimeImmutable('+7 days');
        $apObject = $this->createAPPollObject('First', $endTime, 15, isMultipleChoice: true);
        $apObject['anyOf'][] = $this->createAPPollChoiceObject('Second', 5);

        $poll = $this->pollManager->createFromApObject($apObject);

        self::assertCount(2, $poll->choices);
        self::assertEquals(15, $poll->voterCount);

        $this->entityManager->refresh($poll);

        self::assertCount(2, $poll->choices);
        self::assertEquals(15, $poll->voterCount);
    }

    public function testCreateFromApObjectSetsCorrectDates(): void
    {
        $endTime = new \DateTimeImmutable('2024-12-31 23:59:59');
        $publishedTime = new \DateTimeImmutable('2024-01-01 00:00:00');

        $apObject = $this->createAPPollObject('Option', $endTime, 0, publishedTime: $publishedTime);

        $poll = $this->pollManager->createFromApObject($apObject);

        self::assertEquals($endTime, $poll->endDate);
        self::assertEquals($publishedTime, $poll->createdAt);
    }

    public function testCreateFromApObjectWithClosedInsteadOfEndTime(): void
    {
        $closedTime = new \DateTimeImmutable('2024-06-15 12:00:00');

        $APObject = $this->createAPPollObject('Choice A', $closedTime, 5, useClosed: true);

        $poll = $this->pollManager->createFromApObject($APObject);

        self::assertEquals($closedTime, $poll->endDate);
        self::assertFalse($poll->multipleChoice);
    }

    private function createAPPollChoiceObject(string $name, int $totalItems): array
    {
        return [
            'type' => 'Note',
            'name' => $name,
            'replies' => [
                'type' => 'Collection',
                'totalItems' => $totalItems,
            ],
        ];
    }

    private function createAPPollObject(string $choiceName, \DateTimeImmutable $endTime, int $voterCount, bool $useClosed = false, bool $isMultipleChoice = false, ?\DateTimeImmutable $publishedTime = null): array
    {
        return [
            'type' => 'Question',
            $useClosed ? 'closed' : 'endTime' => $endTime->format(\DateTimeInterface::ATOM),
            'published' => $publishedTime?->format(\DateTimeInterface::ATOM) ?? new \DateTimeImmutable()->format(\DateTimeInterface::ATOM),
            'votersCount' => $voterCount,
            $isMultipleChoice ? 'anyOf' : 'oneOf' => [
                [
                    'type' => 'Note',
                    'name' => $choiceName,
                    'replies' => [
                        'type' => 'Collection',
                        'totalItems' => $voterCount,
                    ],
                ],
            ],
        ];
    }
}
