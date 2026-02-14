<?php

declare(strict_types=1);

namespace App\Controller\Magazine;

use App\Controller\AbstractController;
use App\Entity\Magazine;
use App\Repository\MagazineRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MagazineModController extends AbstractController
{
    public function __invoke(
        #[MapEntity]
        Magazine $magazine,
        MagazineRepository $repository,
        Request $request,
    ): Response {
        $moderators = $repository->findModerators($magazine, $this->getPageNb($request));

        return $this->render(
            'magazine/moderators.html.twig',
            [
                'magazine' => $magazine,
                'moderators' => $moderators,
            ]
        );
    }
}
