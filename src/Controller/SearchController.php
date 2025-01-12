<?php

declare(strict_types=1);

namespace App\Controller;

use App\ActivityPub\ActorHandle;
use App\Entity\Magazine;
use App\Entity\User;
use App\Message\ActivityPub\Inbox\CreateMessage;
use App\MessageHandler\ActivityPub\Inbox\CreateHandler;
use App\Service\ActivityPub\ApHttpClientInterface;
use App\Service\ActivityPubManager;
use App\Service\SearchManager;
use App\Service\SettingsManager;
use App\Service\SubjectOverviewManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchController extends AbstractController
{
    public function __construct(
        private readonly SearchManager $manager,
        private readonly ActivityPubManager $activityPubManager,
        private readonly ApHttpClientInterface $apHttpClient,
        private readonly SubjectOverviewManager $overviewManager,
        private readonly SettingsManager $settingsManager,
        private readonly LoggerInterface $logger,
        private readonly CreateHandler $createHandler,
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

        // looking up handles (users and mags)
        if (str_contains($query, '@') && $this->federatedSearchAllowed()) {
            if ($handle = ActorHandle::parse($query)) {
                $this->logger->debug('searching for a matched webfinger {query}', ['query' => $query]);
                $objects = array_merge($objects, $this->lookupHandle($handle));
            } else {
                $this->logger->debug("query doesn't look like a valid handle...", ['query' => $query]);
            }
        }

        // looking up object by AP id (i.e. urls)
        if (false !== filter_var($query, FILTER_VALIDATE_URL)) {
            $this->logger->debug('Query is a valid url');
            $objects = $this->manager->findByApId($query);
            if (0 === \sizeof($objects)) {
                $body = $this->apHttpClient->getActivityObject($query);
                // the returned id could be different from the query url.
                $postId = $body['id'];
                $objects = $this->manager->findByApId($postId);
                if (0 === \sizeof($objects)) {
                    try {
                        $this->createHandler->doWork(new CreateMessage($body));
                        $objects = $this->manager->findByApId($postId);
                    } catch (\Exception $e) {
                        $this->addFlash('error', $e->getMessage());
                    }
                }
            }
        }

        $user = $this->getUser();
        $res = $this->manager->findPaginated($user, $query, $this->getPageNb($request));

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

    private function federatedSearchAllowed(): bool
    {
        return !$this->settingsManager->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN')
            || $this->getUser();
    }

    private function lookupHandle(ActorHandle $handle): array
    {
        $objects = [];
        $name = $handle->plainHandle();

        try {
            $webfinger = $this->activityPubManager->webfinger($name);
            foreach ($webfinger->getProfileIds() as $profileId) {
                $this->logger->debug('Found "{profileId}" at "{name}"', ['profileId' => $profileId, 'name' => $name]);

                // if actor object exists or successfully created
                $object = $this->activityPubManager->findActorOrCreate($profileId);
                if (!empty($object)) {
                    if ($object instanceof Magazine) {
                        $type = 'magazine';
                    } elseif ($object instanceof User && '!' !== $handle->prefix) {
                        $type = 'user';
                    }

                    $objects[] = [
                        'type' => $type,
                        'object' => $object,
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning(
                'an error occurred during webfinger lookup of "{handle}": {exceptionClass}: {message}',
                [
                    'handle' => $name,
                    'exceptionClass' => \get_class($e),
                    'message' => $e->getMessage(),
                ]
            );
        }

        return $objects;
    }
}
