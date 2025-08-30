<?php

declare(strict_types=1);

namespace App\Controller\Domain;

use App\Controller\AbstractController;
use App\PageView\EntryPageView;
use App\Repository\ContentRepository;
use App\Repository\DomainRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

class DomainFrontController extends AbstractController
{
    public function __construct(
        private readonly ContentRepository $contentRepository,
        private readonly DomainRepository $domainRepository,
    ) {
    }

    public function __invoke(
        ?string $name,
        ?string $sortBy,
        ?string $time,
        #[MapQueryParameter]
        ?string $type,
        Request $request,
        Security $security,
    ): Response {
        if (!$domain = $this->domainRepository->findOneBy(['name' => $name])) {
            throw $this->createNotFoundException();
        }

        $criteria = new EntryPageView($this->getPageNb($request), $security);
        $criteria->showSortOption($criteria->resolveSort($sortBy))
            ->setTime($criteria->resolveTime($time))
            ->setType($criteria->resolveType($type))
            ->setDomain($name);
        $resolvedSort = $criteria->resolveSort($sortBy);
        $criteria->sortOption = $resolvedSort;
        $listing = $this->contentRepository->findByCriteria($criteria);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(
                [
                    'html' => $this->renderView(
                        'entry/_list.html.twig',
                        [
                            'entries' => $listing,
                        ]
                    ),
                ]
            );
        }

        return $this->render(
            'domain/front.html.twig',
            [
                'domain' => $domain,
                'entries' => $listing,
                'criteria' => $criteria,
            ]
        );
    }
}
