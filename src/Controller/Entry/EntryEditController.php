<?php

declare(strict_types=1);

namespace App\Controller\Entry;

use App\Controller\AbstractController;
use App\DTO\EntryDto;
use App\Entity\Entry;
use App\Entity\Magazine;
use App\Form\EntryEditType;
use App\Service\EntryManager;
use App\Service\SettingsManager;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class EntryEditController extends AbstractController
{
    use EntryTemplateTrait;

    public function __construct(
        private readonly EntryManager $manager,
        private readonly LoggerInterface $logger,
        private readonly SettingsManager $settingsManager,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    #[IsGranted('edit', subject: 'entry')]
    public function __invoke(
        #[MapEntity(mapping: ['magazine_name' => 'name'])]
        Magazine $magazine,
        #[MapEntity(id: 'entry_id')]
        Entry $entry,
        Request $request,
    ): Response {
        $dto = $this->manager->createDto($entry);
        $maxBytes = $this->settingsManager->getMaxImageByteString();

        $form = $this->createForm(EntryEditType::class, $dto);
        try {
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                if (!$this->isGranted('create_content', $dto->magazine)) {
                    throw new AccessDeniedHttpException();
                }
                /** @var EntryDto $dto */
                $dto = $form->getData();

                $entry = $this->manager->edit($entry, $dto, $this->getUserOrThrow());

                $this->addFlash('success', 'flash_thread_edit_success');

                return $this->redirectToEntry($entry);
            }
        } catch (\Exception $e) {
            // Show an error to the user
            $this->addFlash('error', 'flash_thread_edit_error');
        }

        return $this->render(
            'entry/edit_entry.html.twig',
            [
                'magazine' => $magazine,
                'entry' => $entry,
                'form' => $form->createView(),
                'maxSize' => $maxBytes,
            ],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }
}
