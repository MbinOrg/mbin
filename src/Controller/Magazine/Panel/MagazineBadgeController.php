<?php

declare(strict_types=1);

namespace App\Controller\Magazine\Panel;

use App\Controller\AbstractController;
use App\DTO\BadgeDto;
use App\Entity\Badge;
use App\Entity\Magazine;
use App\Form\BadgeType;
use App\Repository\MagazineRepository;
use App\Service\BadgeManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MagazineBadgeController extends AbstractController
{
    public function __construct(
        private readonly MagazineRepository $repository,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    #[IsGranted('moderate', subject: 'magazine')]
    public function badges(Magazine $magazine, BadgeManager $manager, Request $request): Response
    {
        $badges = $this->repository->findBadges($magazine);

        $dto = new BadgeDto();

        $form = $this->createForm(BadgeType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dto->magazine = $magazine;
            $manager->create($dto);

            return $this->redirectToRefererOrHome($request);
        }

        return $this->render(
            'magazine/panel/badges.html.twig',
            [
                'badges' => $badges,
                'magazine' => $magazine,
                'form' => $form->createView(),
            ]
        );
    }

    #[IsGranted('ROLE_USER')]
    #[IsGranted('moderate', subject: 'magazine')]
    public function remove(
        #[MapEntity(mapping: ['magazine_name' => 'name'])]
        Magazine $magazine,
        #[MapEntity(id: 'badge_id')]
        Badge $badge,
        BadgeManager $manager,
        Request $request
    ): Response {
        $this->validateCsrf('badge_remove', $request->getPayload()->get('token'));

        $manager->delete($badge);

        return $this->redirectToRefererOrHome($request);
    }
}
