<?php

declare(strict_types=1);

namespace App\Controller\Domain;

use App\Controller\AbstractController;
use App\PageView\EntryCommentPageView;
use App\Repository\DomainRepository;
use App\Repository\EntryCommentRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DomainCommentFrontController extends AbstractController
{
    public function __construct(
        private readonly EntryCommentRepository $commentRepository,
        private readonly DomainRepository $domainRepository,
    ) {
    }

    public function __invoke(string $name, ?string $sortBy, ?string $time, Request $request): Response
    {
        if (!$domain = $this->domainRepository->findOneBy(['name' => $name])) {
            throw $this->createNotFoundException();
        }

        $params = [];
        $criteria = new EntryCommentPageView($this->getPageNb($request));
        $criteria->showSortOption($criteria->resolveSort($sortBy))
            ->setTime($criteria->resolveTime($time))
            ->setDomain($name);

        $params['comments'] = $this->commentRepository->findByCriteria($criteria);
        $params['domain'] = $domain;

        return $this->render(
            'domain/comment/front.html.twig',
            $params
        );
    }
}
