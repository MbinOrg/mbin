<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\ContactDto;
use App\Form\ContactType;
use App\Repository\SiteRepository;
use App\Service\ContactManager;
use App\Service\IpResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ContactController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SiteRepository $repository, ContactManager $manager, IpResolver $ipResolver, Request $request): Response
    {
        $site = $repository->findAll();

        $form = $this->createForm(ContactType::class);
        try {
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                /**
                 * @var ContactDto $dto
                 */
                $dto = $form->getData();
                $dto->ip = $ipResolver->resolve();

                if (!$dto->surname) {
                    $manager->send($dto);
                }

                $this->addFlash('success', 'flash_email_was_sent');

                return $this->redirectToRefererOrHome($request);
            }
        } catch (\Exception $e) {
            // Show an error to the user
            $this->addFlash('error', 'flash_email_failed_to_sent');

            $this->logger->error("there was an exception sending an email: {e} - {m}", ["e" => get_class($e), "m" => $e->getMessage(), "exception" => $e]);

        }

        return $this->render(
            'page/contact.html.twig', [
                'body' => $site[0]->contact ?? '',
                'form' => $form->createView(),
            ]
        );
    }
}
