<?php

declare(strict_types=1);

namespace App\Controller\ActivityPub;

use App\Controller\AbstractController;
use App\Entity\Report;
use App\Factory\ActivityPub\FlagFactory;
use GraphQL\Exception\ArgumentException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends AbstractController
{
    public function __construct(
        private readonly FlagFactory $factory,
    ) {
    }

    public function __invoke(
        #[MapEntity(mapping: ['report_id' => 'uuid'])]
        ?Report $report,
    ): Response {
        if (!$report) {
            throw new ArgumentException('there is no such report');
        }

        $json = $this->factory->build($report, $this->factory->getPublicUrl($report->getSubject()));

        $response = new JsonResponse($json);
        $response->headers->set('Content-Type', 'application/activity+json');

        return $response;
    }
}
