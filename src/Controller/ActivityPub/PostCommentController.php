<?php

declare(strict_types=1);

namespace App\Controller\ActivityPub;

use App\Controller\AbstractController;
use App\Controller\Traits\PrivateContentTrait;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Factory\ActivityPub\PostCommentNoteFactory;
use App\Repository\TagLinkRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PostCommentController extends AbstractController
{
    use PrivateContentTrait;

    public function __construct(
        private readonly PostCommentNoteFactory $commentNoteFactory,
        private readonly TagLinkRepository $tagLinkRepository,
    ) {
    }

    public function __invoke(
        #[MapEntity(mapping: ['magazine_name' => 'name'])]
        Magazine $magazine,
        #[MapEntity(id: 'post_id')]
        Post $post,
        #[MapEntity(id: 'comment_id')]
        PostComment $comment,
        Request $request,
    ): Response {
        if ($comment->apId) {
            return $this->redirect($comment->apId);
        }

        $this->handlePrivateContent($post);

        $response = new JsonResponse($this->commentNoteFactory->create($comment, $this->tagLinkRepository->getTagsOfPostComment($comment), true));

        $response->headers->set('Content-Type', 'application/activity+json');

        return $response;
    }
}
