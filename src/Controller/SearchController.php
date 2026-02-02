<?php

declare(strict_types=1);

namespace App\Controller;

use App\ActivityPub\ActorHandle;
use App\DTO\SearchDto;
use App\Entity\Magazine;
use App\Entity\User;
use App\Form\SearchType;
use App\Message\ActivityPub\Inbox\CreateMessage;
use App\Service\ActivityPub\ApHttpClientInterface;
use App\Service\ActivityPubManager;
use App\Service\SearchManager;
use App\Service\SettingsManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

class SearchController extends AbstractController
{
    public function __construct(
        private readonly SearchManager $manager,
        private readonly ActivityPubManager $activityPubManager,
        private readonly ApHttpClientInterface $apHttpClient,
        private readonly SettingsManager $settingsManager,
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $dto = new SearchDto();
        $form = $this->createForm(SearchType::class, $dto, ['csrf_protection' => false]);
        try {
            $form = $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                /** @var SearchDto $dto */
                $dto = $form->getData();
                $query = trim($dto->q);
                $this->logger->debug('searching for {query}', ['query' => $query]);

                $objects = [];

                // looking up handles (users and mags)
                if (str_contains($query, '@') && $this->federatedSearchAllowed()) {
                    if ($handle = ActorHandle::parse($query)) {
                        $this->logger->debug('searching for a matched webfinger {query}', ['query' => $query]);
                        $objects = $this->lookupHandle($handle);
                    } else {
                        $this->logger->debug("query doesn't look like a valid handle...", ['query' => $query]);
                    }
                }

                // looking up object by AP id (i.e. urls)
                if (false !== filter_var($query, FILTER_VALIDATE_URL) && $this->federatedSearchAllowed()) {
                    $this->logger->debug('Query is a valid url');
                    $objects = $this->findObjectsByApUrl($query);
                }

                $user = $this->getUser();
                $res = $this->manager->findPaginated($user, $query, $this->getPageNb($request), authorId: $dto->user?->getId(), magazineId: $dto->magazine?->getId(), specificType: $dto->type, sinceDate: $dto->since);

                $this->logger->debug('results: {num}', ['num' => $res->count()]);

                return $this->render(
                    'search/front.html.twig',
                    [
                        'objects' => $objects,
                        'results' => $res,
                        'pagination' => $res,
                        'form' => $form->createView(),
                        'q' => $query,
                    ]
                );
            }
        } catch (\Exception $e) {
            $this->logger->error($e);
        }

        return $this->render(
            'search/front.html.twig',
            [
                'objects' => [],
                'results' => [],
                'form' => $form->createView(),
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

    private function findObjectsByApUrl(string $url): array
    {
        $objects = $this->manager->findByApId($url);
        if (0 === \sizeof($objects)) {
            // the url could resolve to a different id.
            $body = $this->apHttpClient->getActivityObject($url);
            $apId = $body['id'];
            $objects = $this->manager->findByApId($apId);
            if (0 === \sizeof($objects)) {
                // maybe it is an entry, post, etc.
                try {
                    // process the message in the sync transport, so that the created content is directly visible
                    $this->bus->dispatch(new CreateMessage($body), [new TransportNamesStamp('sync')]);
                    $objects = $this->manager->findByApId($apId);
                } catch (\Exception $e) {
                    $this->addFlash('error', $e->getMessage());
                }

                if (0 === \sizeof($objects)) {
                    // maybe it is a magazine or user
                    try {
                        $this->activityPubManager->findActorOrCreate($apId);
                        $objects = $this->manager->findByApId($apId);
                    } catch (\Exception $e) {
                        $this->addFlash('error', $e->getMessage());
                    }
                }
            }
        }

        return $this->mapApResultsToViewModel($objects);
    }

    private function mapApResultsToViewModel(array $objects): array
    {
        return array_map(function ($object) {
            if ($object instanceof Magazine) {
                $type = 'magazine';
            } elseif ($object instanceof User) {
                $type = 'user';
            } else {
                $type = 'subject';
            }

            return [
                'type' => $type,
                'object' => $object,
            ];
        }, $objects);
    }
}
