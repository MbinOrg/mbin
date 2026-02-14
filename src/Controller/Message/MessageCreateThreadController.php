<?php

declare(strict_types=1);

namespace App\Controller\Message;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Form\MessageType;
use App\Repository\MessageThreadRepository;
use App\Service\MessageManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MessageCreateThreadController extends AbstractController
{
    public function __construct(
        private readonly MessageManager $manager,
        private readonly MessageThreadRepository $threadRepository,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    #[IsGranted('message', subject: 'receiver')]
    public function __invoke(#[MapEntity] User $receiver, Request $request): Response
    {
        $threads = $this->threadRepository->findByParticipants([$this->getUserOrThrow(), $receiver]);
        if ($threads && \sizeof($threads) > 0) {
            return $this->redirectToRoute('messages_single', ['id' => $threads[0]->getId()]);
        }

        $form = $this->createForm(MessageType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->manager->toThread($form->getData(), $this->getUserOrThrow(), $receiver);

            return $this->redirectToRoute(
                'messages_front'
            );
        }

        return $this->render(
            'user/message.html.twig',
            [
                'user' => $receiver,
                'form' => $form->createView(),
            ],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }
}
