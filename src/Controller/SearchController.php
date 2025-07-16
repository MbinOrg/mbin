<?php

declare(strict_types=1);

namespace App\Controller;

use App\ActivityPub\ActorHandle;
use App\DTO\SearchDto;
use App\Entity\Magazine;
use App\Entity\User;
use App\Form\SearchType;
use App\Message\ActivityPub\Inbox\CreateMessage;
use App\MessageHandler\ActivityPub\Inbox\CreateHandler;
use App\Service\ActivityPub\ApHttpClientInterface;
use App\Service\ActivityPubManager;
use App\Service\SearchManager;
use App\Service\SettingsManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchController extends AbstractController
{
    public function __construct(
        private readonly SearchManager $manager,
        private readonly ActivityPubManager $activityPubManager,
        private readonly ApHttpClientInterface $apHttpClient,
        private readonly SettingsManager $settingsManager,
        private readonly LoggerInterface $logger,
        private readonly CreateHandler $createHandler,
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
                $query = $dto->q;
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
}
