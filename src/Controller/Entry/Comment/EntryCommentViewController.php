<?php

declare(strict_types=1);

namespace App\Controller\Entry\Comment;

use App\Controller\AbstractController;
use App\Controller\Traits\PrivateContentTrait;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Event\Entry\EntryHasBeenSeenEvent;
use App\PageView\EntryCommentPageView;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class EntryCommentViewController extends AbstractController
{
    use PrivateContentTrait;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function __invoke(
        #[MapEntity(mapping: ['magazine_name' => 'name'])]
        Magazine $magazine,
        #[MapEntity(id: 'entry_id')]
        Entry $entry,
        #[MapEntity(id: 'comment_id')]
        ?EntryComment $comment,
        Request $request,
        Security $security,
    ): Response {
        $this->handlePrivateContent($entry);

        // @TODO there is no entry comment has been seen event, maybe
        // it should be added so one comment view does not mark all as read in the same entry
        $this->dispatcher->dispatch(new EntryHasBeenSeenEvent($entry));

        $criteria = new EntryCommentPageView(1, $security);

        return $this->render(
            'entry/comment/view.html.twig',
            [
                'magazine' => $magazine,
                'entry' => $entry,
                'comment' => $comment,
                'criteria' => $criteria,
            ]
        );
    }
}
