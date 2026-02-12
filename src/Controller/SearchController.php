<?php

declare(strict_types=1);

namespace App\Controller;

use App\ActivityPub\ActorHandle;
use App\DTO\SearchDto;
use App\Form\SearchType;
use App\Service\SearchManager;
use App\Service\SettingsManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchController extends AbstractController
{
    public function __construct(
        private readonly SearchManager $manager,
        private readonly SettingsManager $settingsManager,
        private readonly LoggerInterface $logger,
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
                        $objects = $this->manager->findActivityPubActorsByUsername($handle);
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

                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'html' => $this->renderView('search/_list.html.twig', [
                            'results' => $res,
                        ]),
                    ]);
                }

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

    private function findObjectsByApUrl(string $url): array
    {
        $result = $this->manager->findActivityPubObjectsByURL($url);

        foreach ($result['errors'] as $error) {
            /** @var \Exception $error */
            $this->addFlash('error', $error->getMessage());
        }

        return $result['results'];
    }
}
