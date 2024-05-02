<?php

declare(strict_types=1);

namespace App\Controller\Entry;

use App\Controller\AbstractController;
use App\DTO\EntryDto;
use App\Entity\Magazine;
use App\PageView\EntryPageView;
use App\Repository\Criteria;
use App\Repository\TagLinkRepository;
use App\Repository\TagRepository;
use App\Service\EntryCommentManager;
use App\Service\EntryManager;
use App\Service\IpResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EntryCreateController extends AbstractController
{
    use EntryTemplateTrait;
    use EntryFormTrait;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly TagLinkRepository $tagLinkRepository,
        private readonly TagRepository $tagRepository,
        private readonly EntryManager $manager,
        private readonly EntryCommentManager $commentManager,
        private readonly ValidatorInterface $validator,
        private readonly IpResolver $ipResolver
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function __invoke(?Magazine $magazine, ?string $type, Request $request): Response
    {
        $dto = new EntryDto();
        $dto->magazine = $magazine;
        $user = $this->getUserOrThrow();

        $form = $this->createFormByType((new EntryPageView(1))->resolveType($type), $dto);
        try {
            // Could thrown an error on event handlers (eg. onPostSubmit if a user upload an incorrect image)
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                /** @var EntryDto $dto */
                $dto = $form->getData();
                $dto->ip = $this->ipResolver->resolve();

                if (!$this->isGranted('create_content', $dto->magazine)) {
                    throw new AccessDeniedHttpException();
                }

                $entry = $this->manager->create($dto, $this->getUserOrThrow());
                foreach ($dto->tags ?? [] as $tag) {
                    $hashtag = $this->tagRepository->findOneBy(['tag' => $tag]);
                    if (!$hashtag) {
                        $hashtag = $this->tagRepository->create($tag);
                    } elseif ($this->tagLinkRepository->entryHasTag($entry, $hashtag)) {
                        continue;
                    }
                    $this->tagLinkRepository->addTagToEntry($entry, $hashtag);
                }

                $this->addFlash('success', 'flash_thread_new_success');

                return $this->redirectToMagazine(
                    $entry->magazine,
                    Criteria::SORT_NEW
                );
            }

            return $this->render(
                $this->getTemplateName((new EntryPageView(1))->resolveType($type)),
                [
                    'magazine' => $magazine,
                    'user' => $user,
                    'form' => $form->createView(),
                ],
                new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
            );
        } catch (\Exception $e) {
            // Show an error to the user
            $this->addFlash('error', 'flash_thread_new_error');
            $this->logger->error($e);

            return $this->render(
                $this->getTemplateName((new EntryPageView(1))->resolveType($type)),
                [
                    'magazine' => $magazine,
                    'user' => $user,
                    'form' => $form->createView(),
                ],
                new Response(null, 422)
            );
        }
    }
}
