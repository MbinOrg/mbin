<?php

declare(strict_types=1);

namespace App\Controller\Entry;

use App\Controller\AbstractController;
use App\DTO\EntryDto;
use App\Entity\Magazine;
use App\Exception\ImageDownloadTooLargeException;
use App\Exception\InstanceBannedException;
use App\Exception\PostingRestrictedException;
use App\Exception\TagBannedException;
use App\Form\EntryType;
use App\Repository\Criteria;
use App\Repository\TagLinkRepository;
use App\Repository\TagRepository;
use App\Service\EntryCommentManager;
use App\Service\EntryManager;
use App\Service\IpResolver;
use App\Service\SettingsManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EntryCreateController extends AbstractController
{
    use EntryTemplateTrait;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SettingsManager $settingsManager,
        private readonly LoggerInterface $logger,
        private readonly TagLinkRepository $tagLinkRepository,
        private readonly TagRepository $tagRepository,
        private readonly EntryManager $manager,
        private readonly EntryCommentManager $commentManager,
        private readonly ValidatorInterface $validator,
        private readonly IpResolver $ipResolver,
        private readonly Security $security,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function __invoke(?Magazine $magazine, Request $request): Response
    {
        $dto = new EntryDto();
        $dto->magazine = $magazine;
        $user = $this->getUserOrThrow();
        $maxBytes = $this->settingsManager->getMaxImageByteString();

        $form = $this->createForm(EntryType::class, $dto);
        try {
            // Could throw an error on event handlers (e.g. onPostSubmit if a user upload an incorrect image)
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
                $this->getTemplateName(),
                [
                    'magazine' => $magazine,
                    'user' => $user,
                    'form' => $form->createView(),
                    'maxSize' => $maxBytes,
                ],
                new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
            );
        } catch (TagBannedException $e) {
            // Show an error to the user
            $this->addFlash('error', 'flash_thread_tag_banned_error');
            $this->logger->error($e);

            return $this->render(
                $this->getTemplateName(),
                [
                    'magazine' => $magazine,
                    'user' => $user,
                    'form' => $form->createView(),
                    'maxSize' => $maxBytes,
                ],
                new Response(null, 422)
            );
        } catch (InstanceBannedException $e) {
            // Show an error to the user
            $this->addFlash('error', 'flash_thread_instance_banned');
            $this->logger->error($e);

            return $this->render(
                $this->getTemplateName(),
                [
                    'magazine' => $magazine,
                    'user' => $user,
                    'form' => $form->createView(),
                    'maxSize' => $maxBytes,
                ],
                new Response(null, 422)
            );
        } catch (PostingRestrictedException $e) {
            $this->addFlash('error', 'flash_posting_restricted_error');
            $this->logger->error($e);

            return $this->render(
                $this->getTemplateName(),
                [
                    'magazine' => $magazine,
                    'user' => $user,
                    'form' => $form->createView(),
                    'maxSize' => $maxBytes,
                ],
                new Response(null, 422)
            );
        } catch (ImageDownloadTooLargeException $e) {
            $this->addFlash('error', $this->translator->trans('flash_image_download_too_large_error', ['%bytes%' => $maxBytes]));
            $this->logger->error($e);

            return $this->render(
                $this->getTemplateName(),
                [
                    'magazine' => $magazine,
                    'user' => $user,
                    'form' => $form->createView(),
                    'maxSize' => $maxBytes,
                ],
                new Response(null, 422)
            );
        } catch (\Exception $e) {
            // Show an error to the user
            $this->addFlash('error', 'flash_thread_new_error');
            $this->logger->error($e);

            return $this->render(
                $this->getTemplateName(),
                [
                    'magazine' => $magazine,
                    'user' => $user,
                    'form' => $form->createView(),
                    'maxSize' => $maxBytes,
                ],
                new Response(null, 422)
            );
        }
    }
}
