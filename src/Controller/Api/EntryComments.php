<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\ApiDataProvider\DtoPaginator;
use App\Controller\AbstractController;
use App\Entity\Entry;
use App\Factory\EntryCommentFactory;
use App\PageView\EntryCommentPageView;
use App\Repository\EntryCommentRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class EntryComments extends AbstractController
{
    public function __construct(
        private readonly EntryCommentRepository $repository,
        private readonly EntryCommentFactory $factory,
        private readonly RequestStack $request,
        private readonly Security $security,
    ) {
    }

    public function __invoke(Entry $entry)
    {
        try {
            $criteria = new EntryCommentPageView((int) $this->request->getCurrentRequest()->get('p', 1), $this->security);
            $criteria->entry = $entry;
            $criteria->onlyParents = false;

            $comments = $this->repository->findByCriteria($criteria);
        } catch (\Exception $e) {
            return [];
        }

        $dtos = array_map(fn ($comment) => $this->factory->createDto($comment),
            (array) $comments->getCurrentPageResults());

        return new DtoPaginator($dtos, 0, EntryCommentRepository::PER_PAGE, $comments->getNbResults());
    }
}
