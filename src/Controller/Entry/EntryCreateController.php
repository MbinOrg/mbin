<?php

declare(strict_types=1);

namespace App\Controller\Entry;

use App\Controller\AbstractController;
use App\DTO\EntryDto;
use App\Entity\Magazine;
use App\Entity\User;
use App\Exception\ImageDownloadTooLargeException;
use App\Exception\InstanceBannedException;
use App\Exception\PostingRestrictedException;
use App\Exception\TagBannedException;
use App\Factory\ImageFactory;
use App\Form\EntryType;
use App\Repository\Criteria;
use App\Repository\ImageRepository;
use App\Repository\TagLinkRepository;
use App\Repository\TagRepository;
use App\Service\EntryCommentManager;
use App\Service\EntryManager;
use App\Service\IpResolver;
use App\Service\SettingsManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
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
        private readonly ImageRepository $imageRepository,
        private readonly ImageFactory $imageFactory,
        private readonly ValidatorInterface $validator,
        private readonly IpResolver $ipResolver,
        private readonly Security $security,
    ) {
    }

    /**
     * @param string[]|null $tags
     */
    #[IsGranted('ROLE_USER')]
    public function __invoke(
        ?Magazine $magazine,
        #[MapQueryParameter]
        ?string $title,
        #[MapQueryParameter]
        ?string $url,
        #[MapQueryParameter]
        ?string $body,
        #[MapQueryParameter]
        ?string $imageAlt,
        #[MapQueryParameter]
        ?string $isNsfw,
        #[MapQueryParameter]
        ?string $isOc,
        #[MapQueryParameter]
        ?array $tags,
        #[MapQueryParameter]
        ?string $imageHash,
        Request $request,
    ): Response {
        $user = $this->getUserOrThrow();
        $maxBytes = $this->settingsManager->getMaxImageByteString();

        $dto = new EntryDto();
        $dto->magazine = $magazine;
        $dto->title = $title;
        $dto->url = $url;
        $dto->body = $body;
        $dto->imageAlt = $imageAlt;
        $dto->isAdult = '1' === $isNsfw;
        $dto->isOc = '1' === $isOc;
        $dto->tags = $tags;

        if (null !== $imageHash) {
            $img = $this->imageRepository->findOneBySha256(hex2bin($imageHash));
            if (null !== $img) {
                $dto->image = $this->imageFactory->createDto($img);
            } else {
                $form = $this->createForm(EntryType::class, $dto);

                return $this->showFailure('flash_thread_ref_image_not_found', 400, $magazine, $user, $form, $maxBytes);
            }
        }

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
            $this->logger->error($e);

            return $this->showFailure('flash_thread_tag_banned_error', 422, $magazine, $user, $form, $maxBytes);
        } catch (InstanceBannedException $e) {
            $this->logger->error($e);

            return $this->showFailure('flash_thread_instance_banned', 422, $magazine, $user, $form, $maxBytes);
        } catch (PostingRestrictedException $e) {
            $this->logger->error($e);

            return $this->showFailure('flash_posting_restricted_error', 422, $magazine, $user, $form, $maxBytes);
        } catch (ImageDownloadTooLargeException $e) {
            $this->logger->error($e);

            return $this->showFailure(
                $this->translator->trans('flash_image_download_too_large_error', ['%bytes%' => $maxBytes]),
                422,
                $magazine,
                $user,
                $form,
                $maxBytes
            );
        } catch (\Exception $e) {
            $this->logger->error($e);

            return $this->showFailure('flash_thread_new_error', 422, $magazine, $user, $form, $maxBytes);
        }
    }

    private function showFailure(string $flashMessage, int $httpCode, ?Magazine $magazine, User $user, FormInterface $form, string $maxBytes): Response
    {
        $this->addFlash('error', $flashMessage);

        return $this->render(
            $this->getTemplateName(),
            [
                'magazine' => $magazine,
                'user' => $user,
                'form' => $form->createView(),
                'maxSize' => $maxBytes,
            ],
            new Response(null, $httpCode),
        );
    }
}
