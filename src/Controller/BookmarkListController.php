<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\BookmarkListDto;
use App\Entity\BookmarkList;
use App\Form\BookmarkListType;
use App\PageView\EntryPageView;
use App\Repository\BookmarkListRepository;
use App\Repository\BookmarkRepository;
use App\Repository\Criteria;
use App\Service\BookmarkManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class BookmarkListController extends AbstractController
{
    public function __construct(
        private readonly BookmarkListRepository $bookmarkListRepository,
        private readonly BookmarkRepository $bookmarkRepository,
        private readonly BookmarkManager $bookmarkManager,
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function front(
        ?string $list,
        ?string $sortBy,
        ?string $time,
        string $federation,
        #[MapQueryParameter] ?string $type,
        Request $request,
    ): Response {
        $page = $this->getPageNb($request);
        $user = $this->getUserOrThrow();
        $criteria = new EntryPageView($page, $this->security);
        $criteria->setTime($criteria->resolveTime($time));
        $criteria->setType($criteria->resolveType($type));
        $criteria->showSortOption($criteria->resolveSort($sortBy ?? Criteria::SORT_NEW));
        $criteria->setFederation($federation);

        if (null !== $list) {
            $bookmarkList = $this->bookmarkListRepository->findOneByUserAndName($user, $list);
        } else {
            $bookmarkList = $this->bookmarkListRepository->findOneByUserDefault($user);
        }
        $res = $this->bookmarkRepository->findPopulatedByList($bookmarkList, $criteria);
        $objects = $res->getCurrentPageResults();
        $lists = $this->bookmarkListRepository->findByUser($user);

        $this->logger->info('got results in list {l}: {r}', ['l' => $list, 'r' => $objects]);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'html' => $this->renderView('layout/_subject_list.html.twig', [
                    'results' => $objects,
                    'pagination' => $res,
                ]),
            ]);
        }

        return $this->render(
            'bookmark/front.html.twig',
            [
                'criteria' => $criteria,
                'list' => $bookmarkList,
                'lists' => $lists,
                'results' => $objects,
                'pagination' => $res,
            ]
        );
    }

    #[IsGranted('ROLE_USER')]
    public function list(Request $request): Response
    {
        $user = $this->getUserOrThrow();
        $dto = new BookmarkListDto();
        $form = $this->createForm(BookmarkListType::class, $dto);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var BookmarkListDto $dto */
            $dto = $form->getData();
            $list = $this->bookmarkManager->createList($user, $dto->name);
            if ($dto->isDefault) {
                $this->bookmarkListRepository->makeListDefault($user, $list);
            }

            return $this->redirectToRoute('bookmark_lists');
        }

        return $this->render('bookmark/overview.html.twig', [
            'lists' => $this->bookmarkListRepository->findByUser($user),
            'form' => $form->createView(),
        ],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }

    #[IsGranted('ROLE_USER')]
    public function subjectBookmarkMenuListRefresh(int $subject_id, string $subject_type, Request $request): Response
    {
        $user = $this->getUserOrThrow();
        $bookmarkLists = $this->bookmarkListRepository->findByUser($user);
        $subjectClass = BookmarkManager::GetClassFromSubjectType($subject_type);
        $subjectEntity = $this->entityManager->getRepository($subjectClass)->findOneBy(['id' => $subject_id]);
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'html' => $this->renderView('components/_ajax.html.twig', [
                    'component' => 'bookmark_menu_list',
                    'attributes' => [
                        'subject' => $subjectEntity,
                        'subjectClass' => $subjectClass,
                        'bookmarkLists' => $bookmarkLists,
                    ],
                ]
                ),
            ]);
        }

        return $this->redirect($request->headers->get('Referer'));
    }

    #[IsGranted('ROLE_USER')]
    public function makeDefault(#[MapQueryParameter] ?int $makeDefault): Response
    {
        $user = $this->getUserOrThrow();
        $this->logger->info('making list id {id} default for user {u}', ['user' => $user->username, 'id' => $makeDefault]);
        if (null !== $makeDefault) {
            $list = $this->bookmarkListRepository->findOneBy(['id' => $makeDefault]);
            $this->bookmarkListRepository->makeListDefault($user, $list);
        }

        return $this->redirectToRoute('bookmark_lists');
    }

    #[IsGranted('ROLE_USER')]
    public function editList(#[MapEntity] BookmarkList $list, Request $request): Response
    {
        $user = $this->getUserOrThrow();
        $dto = BookmarkListDto::fromList($list);
        $form = $this->createForm(BookmarkListType::class, $dto);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $dto = $form->getData();
            $this->bookmarkListRepository->editList($user, $list, $dto);

            return $this->redirectToRoute('bookmark_lists');
        }

        return $this->render('bookmark/edit.html.twig', [
            'list' => $list,
            'form' => $form->createView(),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    public function deleteList(#[MapEntity] BookmarkList $list): Response
    {
        $user = $this->getUserOrThrow();
        if ($user->getId() !== $list->user->getId()) {
            $this->logger->error('user {u} tried to delete a list that is not his own: {l}', ['u' => $user->username, 'l' => "$list->name ({$list->getId()})"]);
            throw new AccessDeniedHttpException();
        }
        $this->bookmarkListRepository->deleteList($list);

        return $this->redirectToRoute('bookmark_lists');
    }
}
