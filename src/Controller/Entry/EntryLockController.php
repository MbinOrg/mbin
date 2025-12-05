<?php

declare(strict_types=1);

namespace App\Controller\Entry;

use App\Controller\AbstractController;
use App\Entity\Entry;
use App\Entity\Magazine;
use App\Service\EntryManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class EntryLockController extends AbstractController
{
    public function __construct(
        private readonly EntryManager $manager,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    #[IsGranted('lock', subject: 'entry')]
    public function __invoke(
        #[MapEntity(mapping: ['magazine_name' => 'name'])]
        Magazine $magazine,
        #[MapEntity(id: 'entry_id')]
        Entry $entry,
        Request $request,
    ): Response {
        $this->validateCsrf('entry_lock', $request->getPayload()->get('token'));

        $entry = $this->manager->toggleLock($entry, $this->getUserOrThrow());

        $this->addFlash(
            'success',
            $entry->isLocked ? 'flash_thread_lock_success' : 'flash_thread_unlock_success'
        );

        return $this->redirectToRefererOrHome($request);
    }
}
