<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Poll;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Service\ActivityPub\ApHttpClientInterface;
use App\Service\PollManager;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PollVoteController extends AbstractController
{
    public function __construct(
        private readonly PollManager $pollManager,
        private readonly LoggerInterface $logger,
        private readonly ApHttpClientInterface $apHttpClient,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function vote(#[MapEntity] Poll $poll, Request $request): Response
    {
        $user = $this->getUserOrThrow();
        $choices = $request->query->all('choice');

        $content = $this->pollManager->getContentOfPoll($poll);

        if (null === $content) {
            throw new NotFoundHttpException();
        }

        try {
            $this->pollManager->vote($poll, $content, $user, $choices);
        } catch (\Throwable $e) {
            $this->logger->error('There was an error voting on poll {id}: {class} - {m}', [
                'id' => $poll->getId(),
                'class' => \get_class($e),
                'm' => $e->getMessage(),
            ]);
            throw new BadRequestHttpException(previous: $e);
        }

        return $this->redirectToPollContent($poll);
    }

    #[IsGranted('ROLE_USER')]
    public function refreshVoteCounts(#[MapEntity] Poll $poll): Response
    {
        if (null === $poll->getSubject()->apId) {
            throw new BadRequestHttpException('Cannot refresh the vote counts of a local poll');
        }

        $this->apHttpClient->invalidateActivityObjectCache($poll->getSubject()->apId);
        $object = $this->apHttpClient->getActivityObject($poll->getSubject()->apId);
        if ($this->pollManager->hasPollProperties($object)) {
            $this->pollManager->updatePollCounts($poll, $object);
        }

        return $this->redirectToPollContent($poll);
    }

    protected function redirectToPollContent(Poll $poll): Response
    {
        $content = $poll->getSubject();
        if ($content instanceof Entry) {
            return $this->redirectToRoute('entry_single', ['entry_id' => $content->getId(), 'magazine_name' => $content->magazine->name]);
        } elseif ($content instanceof EntryComment) {
            return $this->redirectToRoute('entry_comment_view', ['entry_id' => $content->entry->getId(), 'comment_id' => $content->getId(), 'slug' => '-', 'magazine_name' => $content->magazine->name]);
        } elseif ($content instanceof Post) {
            return $this->redirectToRoute('post_single', ['post_id' => $content->getId(), 'magazine_name' => $content->magazine->name]);
        } elseif ($content instanceof PostComment) {
            return $this->redirectToRoute('post_single', ['post_id' => $content->post->getId(), 'magazine_name' => $content->magazine->name]);
        } else {
            throw new BadRequestHttpException();
        }
    }
}
