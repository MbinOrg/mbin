<?php

declare(strict_types=1);

namespace App\Controller\Entry\Comment;

use App\Controller\AbstractController;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Event\Entry\EntryHasBeenSeenEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class EntryCommentViewController extends AbstractController
{

    public function __construct(
        private readonly RequestStack $requestStack
    ) {
    }

    public function __invoke(
        #[MapEntity(mapping: ['magazine_name' => 'name'])]
        Magazine $magazine,
        #[MapEntity(id: 'entry_id')]
        Entry $entry,
        #[MapEntity(id: 'parent_comment_id')]
        ?EntryComment $parent,
        EventDispatcherInterface $dispatcher,
        Request $request,
    ): Response {

        // @TODO there is no entry comment has been seen event, maybe
        // it should be added so one comment view does not mark all as read in the same entry
        $dispatcher->dispatch(new EntryHasBeenSeenEvent($entry));

        return $this->render(
            'entry/comment/view.html.twig',
            [
                'magazine' => $magazine,
                'entry' => $entry,
                'parent' => $parent,
            ]
        );
    }
}
