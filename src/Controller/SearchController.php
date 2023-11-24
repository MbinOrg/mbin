<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Magazine;
use App\Entity\User;
use App\Message\ActivityPub\Inbox\ActivityMessage;
use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPubManager;
use App\Service\SearchManager;
use App\Service\SettingsManager;
use App\Service\SubjectOverviewManager;
use App\Utils\RegPatterns;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class SearchController extends AbstractController
{
    public function __construct(
        private readonly SearchManager $manager,
        private readonly ActivityPubManager $activityPubManager,
        private readonly MessageBusInterface $bus,
        private readonly ApHttpClient $apHttpClient,
        private readonly SubjectOverviewManager $overviewManager,
        private readonly SettingsManager $settingsManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $query = $request->query->get('q') ? trim($request->query->get('q')) : null;

        if (!$query) {
            return $this->render(
                'search/front.html.twig',
                [
                    'objects' => [],
                    'results' => [],
                    'q' => '',
                ]
            );
        }

        $this->logger->debug('searching for {query}', ['query' => $query]);
        $objects = [];
        if (str_contains($query, '@') && (!$this->settingsManager->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN') || $this->getUser())) {
            $name = str_starts_with($query, '@') ? $query : '@'.$query;
            preg_match(RegPatterns::AP_USER, $name, $matches);
            if (\count(array_filter($matches)) >= 4) {
                $this->logger->debug('searching for a matched webfinger {query}', ['query' => $query]);
                try {
                    $webfinger = $this->activityPubManager->webfinger($name);
                    foreach ($webfinger->getProfileIds() as $profileId) {
                        $this->logger->debug('Found "{pId}" at "{name}"', ['pId' => $profileId, 'name' => $name]);
                        $object = $this->activityPubManager->findActorOrCreate($profileId);
                        // Check if object is not empty
                        if (!empty($object)) {
                            if ($object instanceof Magazine) {
                                $type = 'magazine';
                            } elseif ($object instanceof User) {
                                $type = 'user';
                            }

                            $objects[] = [
                                'type' => $type,
                                'object' => $object,
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('an error occurred during lookup of "{query}": {exClass}: {exMsg}', [
                        'query' => $query,
                        'exClass' => \get_class($e),
                        'exMsg' => $e->getMessage(),
                    ]);
                }
            } else {
                $this->logger->debug("query doesn't match the pattern...", [$matches]);
            }
        }

        if (false !== filter_var($query, FILTER_VALIDATE_URL)) {
            $objects = $this->manager->findByApId($query);
            if (!$objects) {
                $body = $this->apHttpClient->getActivityObject($query, false);
                $this->bus->dispatch(new ActivityMessage($body));
            }
        }

        $res = $this->manager->findPaginated($query, $this->getPageNb($request));

        return $this->render(
            'search/front.html.twig',
            [
                'objects' => $objects,
                'results' => $this->overviewManager->buildList($res),
                'pagination' => $res,
                'q' => $request->query->get('q'),
            ]
        );
    }
}
