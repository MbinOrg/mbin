<?php

declare(strict_types=1);

namespace App\Controller\Entry\Comment;

use App\Controller\AbstractController;
use App\DTO\EntryCommentDto;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Form\EntryCommentType;
use App\PageView\EntryCommentPageView;
use App\Service\EntryCommentManager;
use App\Service\IpResolver;
use App\Service\MentionManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class EntryCommentViewController extends AbstractController
{

    public function __construct(
        private readonly EntryCommentManager $manager,
        private readonly RequestStack $requestStack,
        private readonly IpResolver $ipResolver,
        private readonly MentionManager $mentionManager
    ) {
    }

    public function __invoke(
        #[MapEntity(mapping: ['magazine_name' => 'name'])]
        Magazine $magazine,
        #[MapEntity(id: 'entry_id')]
        Entry $entry,
        #[MapEntity(id: 'parent_comment_id')]
        ?EntryComment $parent,
        Request $request,
    ): Response {
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
