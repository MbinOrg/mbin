<?php

declare(strict_types=1);

namespace App\Controller\Entry\Comment;

use App\Controller\AbstractController;
use App\Entity\Magazine;
use App\PageView\EntryCommentPageView;
use App\Repository\Criteria;
use App\Repository\EntryCommentRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class EntryCommentFrontController extends AbstractController
{
    public function __construct(
        private readonly EntryCommentRepository $repository,
        private readonly Security $security,
    ) {
    }

    public function front(?Magazine $magazine, ?string $sortBy, ?string $time, Request $request, #[MapQueryParameter] ?string $federation): Response
    {
        $params = [];
        $criteria = new EntryCommentPageView($this->getPageNb($request), $this->security);
        $criteria->showSortOption($criteria->resolveSort($sortBy ?? Criteria::SORT_DEFAULT))
            ->setTime($criteria->resolveTime($time));
        $criteria->setFederation($federation);

        if ($magazine) {
            $criteria->magazine = $params['magazine'] = $magazine;
        }

        $params['comments'] = $this->repository->findByCriteria($criteria);
        $params['criteria'] = $criteria;

        return $this->render(
            'entry/comment/front.html.twig',
            $params
        );
    }

    #[IsGranted('ROLE_USER')]
    public function subscribed(?string $sortBy, ?string $time, Request $request): Response
    {
        $params = [];
        $criteria = new EntryCommentPageView($this->getPageNb($request), $this->security);
        $criteria->showSortOption($criteria->resolveSort($sortBy))
            ->setTime($criteria->resolveTime($time));
        $criteria->subscribed = true;

        $params['comments'] = $this->repository->findByCriteria($criteria);
        $params['criteria'] = $criteria;

        return $this->render(
            'entry/comment/front.html.twig',
            $params
        );
    }

    #[IsGranted('ROLE_USER')]
    public function moderated(?string $sortBy, ?string $time, Request $request): Response
    {
        $params = [];
        $criteria = new EntryCommentPageView($this->getPageNb($request), $this->security);
        $criteria->showSortOption($criteria->resolveSort($sortBy))
            ->setTime($criteria->resolveTime($time));
        $criteria->moderated = true;

        $params['comments'] = $this->repository->findByCriteria($criteria);
        $params['criteria'] = $criteria;

        return $this->render(
            'entry/comment/front.html.twig',
            $params
        );
    }

    #[IsGranted('ROLE_USER')]
    public function favourite(?string $sortBy, ?string $time, Request $request): Response
    {
        $params = [];
        $criteria = new EntryCommentPageView($this->getPageNb($request), $this->security);
        $criteria->showSortOption($criteria->resolveSort($sortBy))
            ->setTime($criteria->resolveTime($time));
        $criteria->favourite = true;

        $params['comments'] = $this->repository->findByCriteria($criteria);
        $params['criteria'] = $criteria;

        return $this->render(
            'entry/comment/front.html.twig',
            $params
        );
    }
}
