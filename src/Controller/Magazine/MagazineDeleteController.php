<?php

declare(strict_types=1);

namespace App\Controller\Magazine;

use App\Controller\AbstractController;
use App\Entity\Magazine;
use App\Service\MagazineManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MagazineDeleteController extends AbstractController
{
    public function __construct(private readonly MagazineManager $manager)
    {
    }

    #[IsGranted('ROLE_USER')]
    #[IsGranted('delete', subject: 'magazine')]
    public function delete(#[MapEntity(mapping: ['name' => 'name'])] Magazine $magazine, Request $request): Response
    {
        $this->validateCsrf('magazine_delete', $request->getPayload()->get('token'));

        $this->manager->delete($magazine);

        return $this->redirectToRefererOrHome($request);
    }

    #[IsGranted('ROLE_USER')]
    #[IsGranted('delete', subject: 'magazine')]
    public function restore(#[MapEntity(mapping: ['name' => 'name'])] Magazine $magazine, Request $request): Response
    {
        $this->validateCsrf('magazine_restore', $request->getPayload()->get('token'));

        $this->manager->restore($magazine);

        return $this->redirectToRefererOrHome($request);
    }

    #[IsGranted('ROLE_USER')]
    #[IsGranted('purge', subject: 'magazine')]
    public function purge(#[MapEntity(mapping: ['name' => 'name'])] Magazine $magazine, Request $request): Response
    {
        $this->validateCsrf('magazine_purge', $request->getPayload()->get('token'));

        $this->manager->purge($magazine);

        return $this->redirectToRoute('front');
    }

    #[IsGranted('ROLE_USER')]
    #[IsGranted('purge', subject: 'magazine')]
    public function purgeContent(#[MapEntity(mapping: ['name' => 'name'])] Magazine $magazine, Request $request): Response
    {
        $this->validateCsrf('magazine_purge_content', $request->getPayload()->get('token'));

        $this->manager->purge($magazine, true);

        return $this->redirectToRefererOrHome($request);
    }
}
