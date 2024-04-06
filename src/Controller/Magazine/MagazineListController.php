<?php

declare(strict_types=1);

namespace App\Controller\Magazine;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Form\MagazinePageViewType;
use App\PageView\MagazinePageView;
use App\Repository\Criteria;
use App\Repository\MagazineRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class MagazineListController extends AbstractController
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly MagazineRepository $repository,
    ) {
    }

    public function __invoke(string $sortBy, string $view, Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->tokenStorage->getToken()?->getUser();

        $criteria = new MagazinePageView(
            $this->getPageNb($request),
            $sortBy,
            Criteria::AP_ALL,
            $user?->hideAdult ? MagazinePageView::ADULT_HIDE : MagazinePageView::ADULT_SHOW,
        );

        $form = $this->createForm(MagazinePageViewType::class, $criteria);

        $form->handleRequest($request);

        $magazines = $this->repository->findPaginated($criteria);

        return $this->render(
            'magazine/list_all.html.twig',
            [
                'form' => $form,
                'magazines' => $magazines,
                'view' => $view,
                'criteria' => $criteria,
            ]
        );
    }
}
