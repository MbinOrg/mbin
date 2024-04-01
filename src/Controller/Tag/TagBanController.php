<?php

declare(strict_types=1);

namespace App\Controller\Tag;

use App\Controller\AbstractController;
use App\Repository\TagRepository;
use App\Service\TagManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TagBanController extends AbstractController
{
    public function __construct(
        private readonly TagManager $tagManager,
        private readonly TagRepository $tagRepository,
    ) {
    }

    #[IsGranted('ROLE_ADMIN')]
    public function ban(string $name, Request $request): Response
    {
        $this->validateCsrf('ban', $request->request->get('token'));

        $hashtag = $this->tagRepository->findOneBy(['tag' => $name]);
        if ($hashtag) {
            $this->tagManager->ban($hashtag);

            return $this->redirectToRoute('tag_overview', ['name' => $hashtag->tag]);
        } else {
            throw $this->createNotFoundException();
        }
    }

    #[IsGranted('ROLE_ADMIN')]
    public function unban(string $name, Request $request): Response
    {
        $this->validateCsrf('ban', $request->request->get('token'));

        $hashtag = $this->tagRepository->findOneBy(['tag' => $name]);
        if ($hashtag) {
            $this->tagManager->unban($hashtag);

            return $this->redirectToRoute('tag_overview', ['name' => $hashtag->tag]);
        } else {
            throw $this->createNotFoundException();
        }
    }
}
