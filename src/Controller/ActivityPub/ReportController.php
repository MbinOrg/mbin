<?php

declare(strict_types=1);

namespace App\Controller\ActivityPub;

use App\Controller\AbstractController;
use App\Entity\Report;
use App\Factory\ActivityPub\FlagFactory;
use App\Service\ActivityPub\ActivityJsonBuilder;
use GraphQL\Exception\ArgumentException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends AbstractController
{
    public function __construct(
        private readonly FlagFactory $factory,
        private readonly ActivityJsonBuilder $activityJsonBuilder,
    ) {
    }

    public function __invoke(
        #[MapEntity(mapping: ['report_id' => 'uuid'])]
        Report $report,
        Request $request,
    ): Response {
        if (!$report) {
            throw new ArgumentException('there is no such report');
        }

        $activity = $this->factory->build($report);
        $json = $this->activityJsonBuilder->buildActivityJson($activity);

        $response = new JsonResponse($json);
        $response->headers->set('Content-Type', 'application/activity+json');

        return $response;
    }
}
