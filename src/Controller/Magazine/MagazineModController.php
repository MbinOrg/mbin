<?php

declare(strict_types=1);

namespace App\Controller\Magazine;

use App\Controller\AbstractController;
use App\Entity\Magazine;
use App\Entity\Moderator;
use App\Repository\MagazineRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MagazineModController extends AbstractController
{
    public function __invoke(Magazine $magazine, MagazineRepository $repository, Request $request): Response
    {
        $moderatorsWithoutOwner = [];
        foreach ($repository->findModerators($magazine, $this->getPageNb($request)) as /* @var $mod Moderator */ $mod) {
            // only include the owner if it is a local magazine, for remote magazines the owner is always the admin
            if (!$mod->isOwner or null === $magazine->apId) {
                $moderatorsWithoutOwner[] = $mod;
            }
        }

        return $this->render(
            'magazine/moderators.html.twig',
            [
                'magazine' => $magazine,
                'moderators' => $moderatorsWithoutOwner,
            ]
        );
    }
}
