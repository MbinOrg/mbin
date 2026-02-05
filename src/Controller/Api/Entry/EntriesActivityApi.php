<?php

declare(strict_types=1);

namespace App\Controller\Api\Entry;

use App\Controller\Traits\PrivateContentTrait;
use App\Entity\Entry;
use App\Factory\ContentActivityDtoFactory;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class EntriesActivityApi extends EntriesBaseApi
{
    use PrivateContentTrait;

    public function __invoke(
        #[MapEntity(id: 'entry_id')]
        Entry $entry,
        ContentActivityDtoFactory $dtoFactory,
        RateLimiterFactory $apiReadLimiter,
        RateLimiterFactory $anonymousApiReadLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $this->handlePrivateContent($entry);

        $dto = $dtoFactory->createActivitiesDto($entry);

        return new JsonResponse(
            $dto,
            headers: $headers
        );
    }
}
