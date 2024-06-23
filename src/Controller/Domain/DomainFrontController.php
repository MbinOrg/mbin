<?php

declare(strict_types=1);

namespace App\Controller\Domain;

use App\Controller\AbstractController;
use App\PageView\EntryPageView;
use App\Repository\Criteria;
use App\Repository\DomainRepository;
use App\Repository\EntryRepository;
use Pagerfanta\PagerfantaInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

class DomainFrontController extends AbstractController
{
    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly DomainRepository $domainRepository
    ) {
    }

    public function __invoke(
        ?string $name,
        ?string $sortBy,
        ?string $time,
        #[MapQueryParameter]
        ?string $type,
        Request $request
    ): Response {
        if (!$domain = $this->domainRepository->findOneBy(['name' => $name])) {
            throw $this->createNotFoundException();
        }

        $criteria = new EntryPageView($this->getPageNb($request));
        $criteria->showSortOption($criteria->resolveSort($sortBy))
            ->setTime($criteria->resolveTime($time))
            ->setType($criteria->resolveType($type))
            ->setDomain($name);
        $method = $criteria->resolveSort($sortBy);
        $listing = $this->$method($criteria);

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

    private function hot(EntryPageView $criteria): PagerfantaInterface
    {
        return $this->entryRepository->findByCriteria($criteria->showSortOption(Criteria::SORT_HOT));
    }

    private function top(EntryPageView $criteria): PagerfantaInterface
    {
        return $this->entryRepository->findByCriteria($criteria->showSortOption(Criteria::SORT_TOP));
    }

    private function active(EntryPageView $criteria): PagerfantaInterface
    {
        return $this->entryRepository->findByCriteria($criteria->showSortOption(Criteria::SORT_ACTIVE));
    }

    private function newest(EntryPageView $criteria): PagerfantaInterface
    {
        return $this->entryRepository->findByCriteria($criteria->showSortOption(Criteria::SORT_NEW));
    }

    private function commented(EntryPageView $criteria): PagerfantaInterface
    {
        return $this->entryRepository->findByCriteria($criteria->showSortOption(Criteria::SORT_COMMENTED));
    }
}
