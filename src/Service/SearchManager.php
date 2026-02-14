<?php

declare(strict_types=1);

namespace App\Service;

use App\ActivityPub\ActorHandle;
use App\Entity\Magazine;
use App\Entity\User;
use App\Repository\DomainRepository;
use App\Repository\MagazineRepository;
use App\Repository\SearchRepository;
use App\Service\ActivityPub\ApHttpClientInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

class SearchManager
{
    public function __construct(
        private readonly SearchRepository $repository,
        private readonly MagazineRepository $magazineRepository,
        private readonly DomainRepository $domainRepository,
        private readonly ActivityPubManager $activityPubManager,
        private readonly MessageBusInterface $bus,
        private readonly ApHttpClientInterface $apHttpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Not implemented yet.
     */
    public function findByTagPaginated(string $val, int $page = 1): PagerfantaInterface
    {
        return new Pagerfanta(new ArrayAdapter([]));
    }

    public function findMagazinesPaginated(string $magazine, int $page = 1, int $perPage = MagazineRepository::PER_PAGE): PagerfantaInterface
    {
        return $this->magazineRepository->search($magazine, $page, $perPage);
    }

    public function findDomainsPaginated(string $domain, int $page = 1, int $perPage = DomainRepository::PER_PAGE): Pagerfanta
    {
        return $this->domainRepository->search($domain, $page, $perPage);
    }

    public function findPaginated(
        ?User $queryingUser,
        string $val,
        int $page = 1,
        int $perPage = SearchRepository::PER_PAGE,
        ?int $authorId = null,
        ?int $magazineId = null,
        ?string $specificType = null,
        ?\DateTimeImmutable $sinceDate = null,
    ): PagerfantaInterface {
        return $this->repository->search($queryingUser, $val, $page, authorId: $authorId, magazineId: $magazineId, specificType: $specificType, sinceDate: $sinceDate);
    }

    public function findByApId(string $url): array
    {
        return $this->repository->findByApId($url);
    }

    public function findRelated(string $query): array
    {
        return [];
    }

    /**
     * @param ActorHandle $handle a valid handle (can be obtained from string via ActorHandle::parse())
     *
     * @return array ['type' => 'magazine'|'user', 'object' => Magazine|User][]
     */
    public function findActivityPubActorsByUsername(ActorHandle $handle): array
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
                    } else {
                        $this->logger->error(
                            'Unexpected AP object type: {type} , handle: {handle}',
                            [
                                'type' => \get_class($object),
                                'handle' => $name,
                            ]
                        );
                        continue;
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

    /**
     * Will dispatch a getActivityObject request if a valid URL was provided but no item was found locally.
     *
     * @param string $url a string that may or may not be a URL
     *
     * @return array array ['results' => ['type' => 'magazine'|'user'|'subject', 'object' => Magazine|User|ContentInterface][], 'errors' => Exception[]]
     */
    public function findActivityPubObjectsByURL(string $url): array
    {
        if (false === filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'results' => [],
                'errors' => [],
            ];
        }

        $exceptions = [];
        $objects = $this->findByApId($url);
        if (0 === \sizeof($objects)) {
            // the url could resolve to a different id.
            try {
                $body = $this->apHttpClient->getActivityObject($url);
                $apId = $body['id'];
                $objects = $this->findByApId($apId);
            } catch (\Exception $e) {
                $body = null;
                $apId = $url;
                $exceptions[] = $e;
            }

            if (0 === \sizeof($objects) && null !== $body) {
                // maybe it is an entry, post, etc.
                try {
                    // process the message in the sync transport, so that the created content is directly visible
                    $this->bus->dispatch(new CreateMessage($body), [new TransportNamesStamp('sync')]);
                    $objects = $this->findByApId($apId);
                } catch (\Exception $e) {
                    $exceptions[] = $e;
                }
            }

            if (0 === \sizeof($objects)) {
                // maybe it is a magazine or user
                try {
                    $this->activityPubManager->findActorOrCreate($apId);
                    $objects = $this->findByApId($apId);
                } catch (\Exception $e) {
                    $exceptions[] = $e;
                }
            }
        }

        return [
            'results' => $this->mapApResultsToSearchModel($objects),
            'errors' => $exceptions,
        ];
    }

    private function mapApResultsToSearchModel(array $objects): array
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
