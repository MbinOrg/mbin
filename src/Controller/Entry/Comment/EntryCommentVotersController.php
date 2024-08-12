<?php

declare(strict_types=1);

namespace App\Controller\Entry\Comment;

use App\Controller\AbstractController;
use App\Entity\Contracts\VotableInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Service\SettingsManager;
use App\Utils\DownvotesMode;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class EntryCommentVotersController extends AbstractController
{
    public function __construct(
        private readonly SettingsManager $settingsManager,
    ) {
    }

    public function __invoke(
        #[MapEntity(mapping: ['magazine_name' => 'name'])]
        Magazine $magazine,
        #[MapEntity(id: 'entry_id')]
        Entry $entry,
        #[MapEntity(id: 'comment_id')]
        EntryComment $comment,
        Request $request,
        string $type
    ): Response {
        if ('down' === $type && DownvotesMode::Enabled !== $this->settingsManager->getDownvotesMode()) {
            $votes = [];
        } else {
            $votes = $comment->votes->filter(
                fn ($e) => $e->choice === ('up' === $type ? VotableInterface::VOTE_UP : VotableInterface::VOTE_DOWN)
            );
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'html' => $this->renderView('components/voters_inline.html.twig', [
                    'votes' => $votes,
                    'more' => null,
                ]),
            ]);
        }

        return $this->render('entry/comment/voters.html.twig', [
            'magazine' => $magazine,
            'entry' => $entry,
            'comment' => $comment,
            'votes' => $votes,
        ]);
    }
}
