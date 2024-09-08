<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\BookmarkList;
use App\Repository\BookmarkRepository;
use App\Service\BookmarkManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class BookmarkController extends AbstractController
{
    public function __construct(
        private readonly BookmarkManager $bookmarkManager,
        private readonly BookmarkRepository $bookmarkRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function subjectBookmarkStandard(int $subject_id, string $subject_type, Request $request): Response
    {
        $subjectClass = BookmarkManager::GetClassFromSubjectType($subject_type);
        $subjectEntity = $this->entityManager->getRepository($subjectClass)->findOneBy(['id' => $subject_id]);
        $this->bookmarkManager->addBookmarkToDefaultList($this->getUserOrThrow(), $subjectEntity);
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'html' => $this->renderView('components/_ajax.html.twig', [
                    'component' => 'bookmark_standard',
                    'attributes' => [
                        'subject' => $subjectEntity,
                        'subjectClass' => $subjectClass,
                    ],
                ]
                ),
            ]);
        }

        return $this->redirect($request->headers->get('Referer'));
    }

    #[IsGranted('ROLE_USER')]
    public function subjectBookmarkToList(int $subject_id, string $subject_type, #[MapEntity] BookmarkList $list, Request $request): Response
    {
        $subjectClass = BookmarkManager::GetClassFromSubjectType($subject_type);
        $subjectEntity = $this->entityManager->getRepository($subjectClass)->findOneBy(['id' => $subject_id]);
        $user = $this->getUserOrThrow();
        if ($user->getId() !== $list->user->getId()) {
            throw new AccessDeniedHttpException();
        }
        $this->bookmarkManager->addBookmark($user, $list, $subjectEntity);
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'html' => $this->renderView('components/_ajax.html.twig', [
                    'component' => 'bookmark_list',
                    'attributes' => [
                        'subject' => $subjectEntity,
                        'subjectClass' => $subjectClass,
                        'list' => $list,
                    ],
                ]
                ),
            ]);
        }

        return $this->redirect($request->headers->get('Referer'));
    }

    #[IsGranted('ROLE_USER')]
    public function subjectRemoveBookmarks(int $subject_id, string $subject_type, Request $request): Response
    {
        $subjectClass = BookmarkManager::GetClassFromSubjectType($subject_type);
        $subjectEntity = $this->entityManager->getRepository($subjectClass)->findOneBy(['id' => $subject_id]);
        $this->bookmarkRepository->removeAllBookmarksForContent($this->getUserOrThrow(), $subjectEntity);
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'html' => $this->renderView('components/_ajax.html.twig', [
                    'component' => 'bookmark_standard',
                    'attributes' => [
                        'subject' => $subjectEntity,
                        'subjectClass' => $subjectClass,
                    ],
                ]
                ),
            ]);
        }

        return $this->redirect($request->headers->get('Referer'));
    }

    #[IsGranted('ROLE_USER')]
    public function subjectRemoveBookmarkFromList(int $subject_id, string $subject_type, #[MapEntity] BookmarkList $list, Request $request): Response
    {
        $subjectClass = BookmarkManager::GetClassFromSubjectType($subject_type);
        $subjectEntity = $this->entityManager->getRepository($subjectClass)->findOneBy(['id' => $subject_id]);
        $user = $this->getUserOrThrow();
        if ($user->getId() !== $list->user->getId()) {
            throw new AccessDeniedHttpException();
        }
        $this->bookmarkRepository->removeBookmarkFromList($user, $list, $subjectEntity);
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'html' => $this->renderView('components/_ajax.html.twig', [
                    'component' => 'bookmark_list',
                    'attributes' => [
                        'subject' => $subjectEntity,
                        'subjectClass' => $subjectClass,
                        'list' => $list,
                    ],
                ]
                ),
            ]);
        }

        return $this->redirect($request->headers->get('Referer'));
    }
}
