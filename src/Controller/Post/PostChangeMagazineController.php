<?php

declare(strict_types=1);

namespace App\Controller\Post;

use App\Controller\AbstractController;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Repository\MagazineRepository;
use App\Service\PostManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PostChangeMagazineController extends AbstractController
{
    public function __construct(
        private readonly PostManager $manager,
        private readonly MagazineRepository $repository
    ) {
    }

    #[IsGranted('moderate', 'post')]
    public function __invoke(
        #[MapEntity(mapping: ['magazine_name' => 'name'])]
        Magazine $magazine,
        #[MapEntity(id: 'post_id')]
        Post $post,
        Request $request
    ): Response {
        $this->validateCsrf('change_magazine', $request->getPayload()->get('token'));

        $newMagazine = $this->repository->findOneByName($request->get('change_magazine')['new_magazine']);

        $this->manager->changeMagazine($post, $newMagazine);

        return $this->redirectToRefererOrHome($request);
    }
}
