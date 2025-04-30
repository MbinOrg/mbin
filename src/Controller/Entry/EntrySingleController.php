<?php

declare(strict_types=1);

namespace App\Controller\Entry;

use App\Controller\AbstractController;
use App\Controller\Traits\PrivateContentTrait;
use App\Controller\User\ThemeSettingsController;
use App\DTO\EntryCommentDto;
use App\Entity\Entry;
use App\Entity\Magazine;
use App\Event\Entry\EntryHasBeenSeenEvent;
use App\Form\EntryCommentType;
use App\PageView\EntryCommentPageView;
use App\Repository\Criteria;
use App\Repository\EntryCommentRepository;
use App\Service\MentionManager;
use Pagerfanta\PagerfantaInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class EntrySingleController extends AbstractController
{
    use PrivateContentTrait;

    public function __invoke(
        #[MapEntity(mapping: ['magazine_name' => 'name'])]
        Magazine $magazine,
        #[MapEntity(id: 'entry_id')]
        Entry $entry,
        ?string $sortBy,
        EntryCommentRepository $repository,
        EventDispatcherInterface $dispatcher,
        MentionManager $mentionManager,
        Request $request,
        Security $security,
    ): Response {
        if ($entry->magazine !== $magazine) {
            return $this->redirectToRoute(
                'entry_single',
                ['magazine_name' => $entry->magazine->name, 'entry_id' => $entry->getId(), 'slug' => $entry->slug],
                301
            );
        }

        $response = new Response();
        if ($entry->apId && $entry->user->apId) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        $this->handlePrivateContent($entry);

        $criteria = new EntryCommentPageView($this->getPageNb($request), $security);
        $criteria->showSortOption($criteria->resolveSort($sortBy));
        $criteria->entry = $entry;

        if (ThemeSettingsController::CHAT === $request->cookies->get(
            ThemeSettingsController::ENTRY_COMMENTS_VIEW
        )) {
            $criteria->showSortOption(Criteria::SORT_OLD);
            $criteria->perPage = 100;
            $criteria->onlyParents = false;
        }

        $comments = $repository->findByCriteria($criteria);

        $commentObjects = [...$comments->getCurrentPageResults()];
        $repository->hydrate(...$commentObjects);
        $repository->hydrateChildren(...$commentObjects);

        $dispatcher->dispatch(new EntryHasBeenSeenEvent($entry));

        if ($request->isXmlHttpRequest()) {
            return $this->getJsonResponse($magazine, $entry, $comments);
        }

        $user = $this->getUser();

        $dto = new EntryCommentDto();
        if ($user && $user->addMentionsEntries && $entry->user !== $user) {
            $dto->body = $mentionManager->addHandle([$entry->user->username])[0];
        }

        return $this->render(
            'entry/single.html.twig',
            [
                'user' => $user,
                'magazine' => $magazine,
                'comments' => $comments,
                'entry' => $entry,
                'criteria' => $criteria,
                'form' => $this->createForm(EntryCommentType::class, $dto, [
                    'action' => $this->generateUrl(
                        'entry_comment_create',
                        [
                            'magazine_name' => $entry->magazine->name,
                            'entry_id' => $entry->getId(),
                        ]
                    ),
                    'parentLanguage' => $entry->lang,
                ])->createView(),
            ],
            $response
        );
    }

    private function getJsonResponse(Magazine $magazine, Entry $entry, PagerfantaInterface $comments): JsonResponse
    {
        return new JsonResponse(
            [
                'html' => $this->renderView(
                    'entry/_single_popup.html.twig',
                    [
                        'magazine' => $magazine,
                        'comments' => $comments,
                        'entry' => $entry,
                    ]
                ),
            ]
        );
    }
}
