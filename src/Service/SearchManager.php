<?php

declare(strict_types=1);

namespace App\Service;

use App\ActivityPub\ActorHandle;
use App\Entity\Contracts\ContentInterface;
use App\Entity\Magazine;
use App\Entity\User;
use App\Message\ActivityPub\Inbox\ActivityMessage;
use App\Message\ActivityPub\Inbox\CreateMessage;
use App\Repository\DomainRepository;
use App\Repository\MagazineRepository;
use App\Repository\SearchRepository;
use App\Service\ActivityPub\ApHttpClientInterface;
use App\Utils\RegPatterns;
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
        return $this->repository->search($queryingUser, $val, $page, authorId: $authorId, magazineId: $magazineId, specificType: $specificType, sinceDate: $sinceDate, perPage: $perPage);
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
     * Tries to find the actor or object in the DB, else will dispatch a getActorObject or getActivityObject request.
     *
     * @param string $handleOrUrl a string that may be a handle or AP URL
     *
     * @return array{'results': array{'type': 'magazine'|'user'|'subject', 'object': Magazine|User|ContentInterface}, 'errors': \Throwable[]}
     */
    public function findActivityPubActorsOrObjects(string $handleOrUrl): array
    {
        $handle = ActorHandle::parse($handleOrUrl);
        if (null !== $handle) {
            $handleOrUrl = $handle->plainHandle();
            $isUrl = false;
        } elseif (filter_var($handleOrUrl, FILTER_VALIDATE_URL)) {
            $isUrl = true;
        } else {
            return [
                'results' => [],
                'errors' => [],
            ];
        }

        // try resolving it as an actor
        try {
            $actor = $this->activityPubManager->findActorOrCreate($handleOrUrl);
            if (null !== $actor) {
                $objects = $this->mapApResultsToSearchModel([$actor]);

                return [
                    'results' => $objects,
                    'errors' => [],
                ];
            } elseif (!$isUrl) {
                // lookup of handle failed -> give up
                return [
                    'results' => [],
                    'errors' => [],
                ];
            }
        } catch (\Throwable $e) {
            if (!$isUrl) {
                // lookup of handle failed -> give up
                return [
                    'results' => [],
                    'errors' => [$e],
                ];
            }
        }

        $url = $handleOrUrl;
        $exceptions = [];
        $objects = $this->findByApId($url);
        if (0 === \sizeof($objects)) {
            // the url could resolve to a different id.
            try {
                $body = $this->apHttpClient->getActivityObject($url);
                $apId = $body['id'];
                $objects = $this->findByApId($apId);
            } catch (\Throwable $e) {
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
                } catch (\Throwable $e) {
                    $exceptions[] = $e;
                }
            }

            if (0 === \sizeof($objects)) {
                // maybe it is a magazine or user
                try {
                    $this->activityPubManager->findActorOrCreate($apId);
                    $objects = $this->findByApId($apId);
                } catch (\Throwable $e) {
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

    // region deprecated functions kept for API compatibility
    /**
     * @param string $query One or more canonical ActivityPub usernames, such as kbinMeta@kbin.social or @ernest@kbin.social (anything that matches RegPatterns::AP_USER)
     *
     * @return array a list of magazines or users that were found using the given identifiers, empty if none were found or no @ is in the query
     */
    public function findActivityPubActorsByUsername(string $query): array
    {
        if (false === str_contains($query, '@')) {
            return [];
        }

        $objects = [];
        $name = str_starts_with($query, '!') ? '@'.substr($query, 1) : $query;
        $name = str_starts_with($name, '@') ? $name : '@'.$name;
        preg_match(RegPatterns::AP_USER, $name, $matches);
        if (\count(array_filter($matches)) >= 4) {
            try {
                $webfinger = $this->activityPubManager->webfinger($name);
                foreach ($webfinger->getProfileIds() as $profileId) {
                    $object = $this->activityPubManager->findActorOrCreate($profileId);
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
            }
        }

        return $objects ?? [];
    }

    /**
     * @param string $query a string that may or may not be a URL
     *
     * @return array A list of objects found by the given query, or an empty array if none were found.
     *               Will dispatch a getActivityObject request if a valid URL was provided but no item was found
     *               locally.
     */
    public function findActivityPubObjectsByURL(string $query): array
    {
        if (false === filter_var($query, FILTER_VALIDATE_URL)) {
            return [];
        }

        $objects = $this->findByApId($query);
        if (!$objects) {
            $body = $this->apHttpClient->getActivityObject($query, false);
            $this->bus->dispatch(new ActivityMessage($body));
        }

        return $objects ?? [];
    }
    // endregion
}
