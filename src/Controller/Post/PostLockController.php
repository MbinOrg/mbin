<?php

declare(strict_types=1);

namespace App\Controller\Post;

use App\Controller\AbstractController;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Service\PostManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PostLockController extends AbstractController
{
    public function __construct(
        private readonly PostManager $manager,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    #[IsGranted('lock', subject: 'post')]
    public function __invoke(
        #[MapEntity(mapping: ['magazine_name' => 'name'])]
        Magazine $magazine,
        #[MapEntity(id: 'post_id')]
        Post $post,
        Request $request,
    ): Response {
        $this->validateCsrf('post_lock', $request->getPayload()->get('token'));

        $entry = $this->manager->toggleLock($post, $this->getUserOrThrow());

        $this->addFlash(
            'success',
            $entry->isLocked ? 'flash_post_lock_success' : 'flash_post_unlock_success'
        );

        return $this->redirectToRefererOrHome($request);
    }
}
